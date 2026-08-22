# Architecture Specifications

> **Status.** Normative v1 target specifications for the layout stage. The current `writer/` library implements a proper subset. These documents describe **where the system is going**, not where it is today.
>
> Mirrored from https://github.com/edgecase123/report-writer/tree/main/docs/architecture. Fetch upstream via `gh api repos/edgecase123/report-writer/contents/docs/architecture` when you need to check for drift.

---

## Contents

| File | What it covers |
|---|---|
| [fill-to-layout-schema.md](fill-to-layout-schema.md) | Contract between the Fill and Layout stages, subreport-as-container model, band splitting rules, minimal pseudocode |
| [layout-algorithm-spec.md](layout-algorithm-spec.md) | Draft normative spec (MUST/SHOULD language) for the v1 layout engine — invariants, processing order, keep rules, absolute placement, determinism |
| [test-cases-future.md](test-cases-future.md) | Two canonical layout tests: single-band split across pages, subreport container split at child-band boundary — assert the v1 output shape |
| [user-scripting.md](user-scripting.md) | Hard future requirement: PHP/TypeScript scripting in band hooks and `computed` content — threat model, five design directions with trade-offs, migration path |

---

## What the current library implements (as of 2026-08-22)

Everything described in these specs falls into three buckets:

### ✅ Shipped

- Single-pass pagination over a normalized flow list of bands (`writer/src/Layout/LayoutService.php`)
- Fit test → place / start new page
- Single-element splittable text bands split at line boundaries
- Deterministic output for identical input
- Fill / Layout separation (the governing principle at the bottom of the specs)
- `ElementExceedsPageException` diagnostic when a band exceeds a single page

### ⚠️ Partial (functionally present but shape doesn't match spec)

- **Subreports.** `writer/src/Layout/Flattener.php` inlines subreport bands into the parent flow *before* layout runs, then paginates the flat list. The spec requires subreports to remain containers through pagination, preserving `parent_element_instance_id` and `container_type` on child fragments. The current impl loses this linkage.
- **Continuation fragments.** When a splittable band splits, the current impl emits two separate `PositionedElement`s with no `fragment_id` or `continues_on_next_page` linkage. The spec requires stable fragment identity across pages.

### ❌ Not shipped

- Keep-together / keep-with-next / group-keep-with-first-detail rules
- Forced page breaks before/after band (`page_break_before`, `page_break_after` flags)
- Page headers and page footers (reserved-space at top/bottom of each page)
- Stretchable elements with content-measured heights (all element heights are fixed at construction)
- Non-text element kinds beyond `TextContent` + `SubreportContent` (no image/shape/line)
- Emergency overflow policy for oversized non-splittable content
- Layout diagnostics with traceability IDs beyond the single `ElementExceedsPageException`

---

## How this relates to the developer docs

The developer documentation under `docs/01-overview/`, `docs/03-concepts/`, `docs/04-pipeline/`, and `docs/05-authoring/` describes **the library as it is today**. This `architecture/` folder describes **the library as v1 aims to be**. When a doc under `04-pipeline/` (planned) reaches Layout, it will describe today's algorithm and cross-reference this folder as the target.

If you're implementing pipeline changes, work from these specs. If you're using the library today, work from the developer docs — they describe the actual code.

---

## Keeping these docs in sync with upstream

These files are copies of an upstream location. Check for drift with:

```bash
gh api repos/edgecase123/report-writer/contents/docs/architecture/fill-to-layout-schema.md --jq '.sha'
```

Compare against the file on disk. If upstream has changed, refetch and re-apply the local status header. Upstream is the source of truth for the spec content; the status header is local.
