# Implementation Plans

Ordered plans for executing the design captured in [`../specs/`](../specs/) and [`../../tickets/`](../../tickets/). Each plan produces working, testable software on its own.

**Skill entry points:** invoke `superpowers:writing-plans` on the next unfired plan below, or `superpowers:subagent-driven-development` (in-session execution) / `superpowers:executing-plans` (parallel-session execution) on an already-written plan.

## Sub-project A — Standalone runtime + JSON split-screen builder

Sub-project A ([Ticket 012](../../tickets/012-implement-standalone-runtime-subproject-a.md)) is too big for one plan (3–6 weeks of work). Broken into 7 sub-plans, each producing something working. Ordering is important — later sub-plans depend on earlier ones landing.

| # | Plan | What ships | Depends on | Plan file | Status |
|---|---|---|---|---|---|
| A1 | Library housekeeping | Library renamed to `edgecase123/report-writer`, dead code removed, aggregate math consolidated, `AggregateFunction` extracted, `DefinitionFiller::onBand` immutability fixed. Library still passes existing tests, just leaner and correctly named. Closes Tickets 001, 002, 006, 010. | — | [`2026-08-22-a1-library-housekeeping.md`](2026-08-22-a1-library-housekeeping.md) | ✅ Complete (2026-08-22, commits `28827a1`–`d105956`) |
| A2 | Slim host skeleton + one report end-to-end | New `writer-app/` with Slim 4 routing + `Container.php` + `ReportRegistry` + `SqliteDailySalesProvider` + `DailySalesFiller` + `ReportController`. One report (**Daily Sales**, `ReportBuilder`-based) renders at `GET /api/reports/daily-sales?date=…`. Docker Compose runs it (`report-writer-php` container, `:8090`). The 4 Slim smoke tests land. This is the "prove Slim wiring works" milestone. | A1 | *(not yet written)* | ⏭ Next |
| A3 | Remaining 5 sample reports | Sales by Category (JSON `DefinitionFiller`), Sales by Category → Item (`ReportBuilder`, nested groups), Open Tabs (JSON), Register Close (JSON, multi-data-source), Full Menu Book (custom `ReportFillerInterface`, subreport + splittable text + `ComputedExpression` + `onBand`). Docker Compose finalized. Coffee-shop seed complete (~90 days, deterministic). | A2 | *(not yet written)* | Pending |
| A4 | Frontend viewer wired to new API + router | Hash-based mini-router (`router.ts`), `HomeLanding.vue`, viewer route migrated from `data-report-url` attribute to router-derived. `report-writer-vite` container (`:5174`) + Vite `/api` proxy. Viewer renders every report from A3. | A3 | *(not yet written)* | Pending |
| A5 | Builder page (split-screen JSON editor) | `BuilderPage.vue`, `JsonEditor.vue` (CodeMirror 6), split-screen iframe preview, debounced auto-preview, autocomplete Levels A+B, snippets, linter, `state/builderState.ts`, `state/schemaState.ts`, `state/routerState.ts`, `editor/completions.ts`, `editor/snippets.ts`, drafts CRUD + `template_drafts` table + `/api/drafts/*` endpoints. | A4 | *(not yet written)* | Pending |
| A6 | Testing infrastructure | `writer-app/tests/Fixtures/coffee-shop-mini.sql`, `assertReportSnapshot()` helper, 6 snapshot tests (one per report), remaining Slim smoke tests, determinism test. Fixtures separate from demo seed. All three test layers green. | A3 + A5 | *(not yet written)* | Pending |
| A7 | Handoff docs | `docs/02-setup/quickstart.md`, `docs/02-setup/docker.md`, `docs/02-setup/environment.md`, `docs/06-runtime/*`, `docs/07-frontend/*`, `docs/08-testing/*`, `docs/handoff/adoption.md` (Symfony 5.x/7 + Laravel 10+ + plain-PHP wiring examples per [ADR-013](../../09-conventions/decisions/013-framework-agnostic-library.md)). Update repo-root `README.md` with the demo quickstart. | A6 | *(not yet written)* | Pending |

## Sub-project B — Structured form builder UI

Own brainstorming session (invoke `superpowers:brainstorming`), then its own plan(s). Deferred until Sub-project A ships. See [Ticket 013](../../tickets/013-brainstorm-structured-form-builder-subproject-b.md).

## Backlog

Epic-level tickets that haven't been scheduled yet and would each spawn their own brainstorm + plan sequences when they are:

- [Ticket 014](../../tickets/014-implement-v1-layout-engine.md) — Implement v1 layout engine per `docs/architecture/` specs (fragment IDs on continuations, keep rules, forced page breaks, page headers/footers, subreport-as-container pagination with preserved parent linkage, stretchable elements).
- [Ticket 015](../../tickets/015-implement-user-scripting.md) — Implement user scripting per `docs/architecture/user-scripting.md` (PHP or TypeScript sandbox for band hooks, computed content, and other pipeline seams; import-based, restricted-context for template surface).

## How to pick up A2 in a fresh session

Say something like:

> Invoke `superpowers:writing-plans` on Sub-project A2 (Slim host skeleton + one report end-to-end). Scope is defined in `docs/superpowers/plans/README.md`. Use A1's plan (`docs/superpowers/plans/2026-08-22-a1-library-housekeeping.md`) as the shape/style reference. Design context: `docs/superpowers/specs/2026-08-22-standalone-report-writer-design.md` + `docs/tickets/012-implement-standalone-runtime-subproject-a.md` + relevant ADRs.

The writing-plans skill will read those files itself. Nothing else needs handoff.
