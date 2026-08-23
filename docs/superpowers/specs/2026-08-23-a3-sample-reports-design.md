# A3 — Five Sample Reports + Coffee-shop Seed Extension

**Status:** Approved design, ready for implementation plan
**Date:** 2026-08-23
**Ticket:** [012 — Sub-project A epic](../../tickets/012-implement-standalone-runtime-subproject-a.md) (section 2)
**Scope:** `writer-app/database/`, `writer-app/src/Reports/`, `writer-app/tests/`, one JSON template repository directory (path chosen in A3.2)

## Context

Ticket 012 § 2 approved 2026-08-22 lists six sample reports for Sub-project A. Daily Sales shipped in A2. This spec covers **A3 = the remaining five**, plus the schema and seed extensions required to feed them, sequenced into six sub-tickets.

The epic-level *what* is not re-litigated here. This spec captures only the sequencing decisions, per-report specifics, and testing shape that the implementation plan needs.

## Sub-ticket sequence

Ordered simple → complex so each ticket lands on top of a green predecessor.

| # | Sub-ticket | Deliverable | Depends on |
|---|-----------|-------------|-----------|
| **A3.1** | Schema + seed extension | `staff`, `payments` tables; staff-per-order + payments-per-closed-order in seed; `SeedDeterminismTest` still green | — |
| **A3.2** | Sales by Category | `DefinitionFiller` JSON, one-level grouping, sum aggregate; establishes the JSON template repository location and loading pattern | — (uses existing tables) |
| **A3.3** | Open Tabs | `DefinitionFiller` JSON, filter (`closed_at IS NULL`), no grouping; second JSON report to confirm the A3.2 pattern generalizes | A3.2 (template loader) |
| **A3.4** | Sales by Category → Item | `ReportBuilder`, two-level nested groups (category → item); first `ReportBuilder`-based sample in A3 | — |
| **A3.5** | Register Close | `DefinitionFiller` JSON, multi data-source (`orders` + `payments`), split-by-payment-method summary | A3.1 (payments seed), A3.3 |
| **A3.6** | Full Menu Book — kitchen sink | Custom `ReportFillerInterface`; subreports (per category); splittable text (long descriptions); `ComputedExpression`; `onBand` hook | A3.1 (if descriptions are added there) or self-contained |

## Per-report notes

### A3.1 — Schema + seed

- `staff (id, name, role)` — small fixed set (~6 rows: baristas, shift leads, manager).
- `payments (id, order_id, method, amount_cents, taken_at, staff_id)` — one row per closed order (all-cash is fine for v1; add `card`, `mobile` for method-mix in A3.5).
- Seed extension is additive: existing tables and existing row counts stay identical. New tables get populated in the same deterministic pass.
- `SeedDeterminismTest` verifies byte-identical seed across two runs — extend to cover new tables.

### A3.2 — Sales by Category (JSON)

- **JSON template location:** `writer-app/templates/sales-by-category.json` (this ticket picks the directory).
- **TemplateLoader wiring:** `writer-app/src/Reports/JsonTemplateRepository.php` — a thin adapter that reads a directory of `.json` files and hands them to `ReportWriter\Template\TemplateLoader` on demand. Registered in `Container.php`.
- **Data source:** `SqliteSalesByCategoryProvider` — one row per category with `category_name, total_cents`, ordered by `total_cents DESC`.
- **Report:** columns `Category | Total`; footer sum on `Total`.

### A3.3 — Open Tabs (JSON)

- Uses the JsonTemplateRepository from A3.2.
- **Data source:** `SqliteOpenTabsProvider` — one row per `orders` row where `closed_at IS NULL`, with `order_id, opened_at, running_total_cents`.
- **Report:** columns `Order | Opened | Running Total`; no grouping; no footer aggregate.
- **Param:** none (or optional `as_of` timestamp; keep to none for simplicity).

### A3.4 — Sales by Category → Item (ReportBuilder)

- **Filler:** `SalesByCategoryItemFiller implements ReportFillerInterface`, using `ReportBuilder::groupBy(...)->groupBy(...)` internally.
- **Data source:** `SqliteSalesByCategoryItemProvider` — one row per `(category_id, item_id)` with `category_name, item_name, quantity_sold, total_cents`.
- **Report:** two-level groups; per-item detail line; per-category subtotal; grand total footer.
- **Rationale for ReportBuilder:** DefinitionFiller supports only one grouping level (CLAUDE.md § "Two fillers, same output"), so any report that needs nested groups must use ReportBuilder.

### A3.5 — Register Close (multi data-source JSON)

- Uses JsonTemplateRepository. Two named data sources in the template:
  - `orders` — closed orders on `params.date`
  - `payments` — payments taken on `params.date`
- **Providers:** `SqliteClosedOrdersProvider` and `SqliteDailyPaymentsProvider` (or reuse existing `SqliteDailySalesProvider` if the row shape suffices).
- **Report layout:** header "Register Close — {date} — {staff_name}"; orders detail block; payment-method summary block (cash / card / mobile subtotals) using `AggregateExpression`.
- **Param:** `date` (required), `staff_id` (optional; if provided, filter both data sources).

### A3.6 — Full Menu Book (kitchen sink)

- **Filler:** custom `MenuBookFiller implements ReportFillerInterface` — hand-rolled, does not use `ReportBuilder` shortcuts.
- **Features exercised (must hit all — that's the point):**
  - `SubreportContent` per category — outer report iterates categories, each category renders an inline subreport of its items.
  - Splittable text — a per-category description paragraph that can wrap across a page boundary.
  - `ComputedExpression` — e.g. a "margin %" cell computed from `unit_price_cents` and a fake COGS constant.
  - `onBand` hook — post-build tweak (e.g. suppress the category-header band for categories with zero items).
- **Data source:** `SqliteMenuBookProvider` — categories with items nested (either a single ragged fetch, or two providers wired together — implementer's call).
- **Schema addition:** `categories.description TEXT` (may push to A3.1 or accept a small trailing schema tweak in A3.6 itself).

## Registry & routing

Each sub-ticket that lands a report also lands:

- One `ReportDefinition` entry in `ReportRegistry` (id, label, filler class, param specs).
- Wiring in `Container.php` for its provider(s).
- The report is reachable at `/api/reports/{id}` via the existing `ReportController` — no controller changes.

## Testing

Follows ticket 012 § 6 pattern (no re-litigation):

- **Snapshot test per report** against `writer-app/tests/Fixtures/coffee-shop-mini.sql`. Assertion form: `render($filler, $params) === expected_html`. Snapshots live at `writer-app/tests/Snapshots/{report-id}.html`.
- Extend `coffee-shop-mini.sql` in A3.1 to cover new tables with a handful of deterministic rows (enough that each report has non-empty output and exercises its edge cases — e.g. Open Tabs needs at least one row with `closed_at IS NULL`).
- No new Slim smoke tests unless routing changes (it shouldn't).

## Out of scope for A3

- `template_drafts` table + `/api/drafts` CRUD — belongs to A5.
- Any frontend work (Builder UI, JSON editor, autocomplete) — that's A4/A5.
- Handoff docs (`docs/handoff/adoption.md`) — deferred per ADR-011 (docs after implementation).
- Additional payment methods beyond cash/card/mobile — keep the taxonomy small.

## Open questions

None load-bearing; implementer's-choice moments called out inline (e.g. JsonTemplateRepository directory name, whether A3.6's category description column joins A3.1 or comes with A3.6).
