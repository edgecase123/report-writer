# Standalone Report-Writer — Sub-project A Design

**Date:** 2026-08-22
**Status:** Design complete, ready for implementation planning
**Author:** lee (owner) + Claude (facilitator)

---

## Purpose of this document

This is an **index-and-pointer spec**, not a monolithic design doc. Per [ADR-011 (docs-after-implementation)](../../09-conventions/decisions/011-docs-after-implementation.md), the design decisions from the 2026-08-22 brainstorming session were captured across ADRs, tickets, and architecture specs rather than a single spec file. This document exists as a single entry point for anyone (including the `writing-plans` skill) needing to consume the full design in one place.

## Scope

**Sub-project A** — the standalone runtime + JSON split-screen builder for report-writer. Turns the existing library (`writer/`) + existing viewer (`frontend/`) into a `docker compose up`-able end-to-end demonstration, with an inline template editor.

**Not in scope:**
- Sub-project B (structured form builder) — deferred, see [Ticket 013](../../tickets/013-brainstorm-structured-form-builder-subproject-b.md)
- v1 layout engine (fragment IDs, keep rules, subreport-as-container) — deferred, see [Ticket 014](../../tickets/014-implement-v1-layout-engine.md)
- User scripting (PHP/TS in band hooks with sandboxing) — deferred, see [Ticket 015](../../tickets/015-implement-user-scripting.md)

## Load-bearing constraint

Per [ADR-013](../../09-conventions/decisions/013-framework-agnostic-library.md), the library (`writer/`) is framework-agnostic PHP. Consumers can adopt it into Symfony 5.x/7, Laravel 10+, or plain PHP with no framework-specific coupling in the library itself. **Everything Sub-project A adds under `writer-app/`, `docker/`, `docker-compose.yml`, and `frontend/package.json`'s build wiring is dev/test/demo scaffolding only** — throwaway from any real consumer's perspective. The `writer-app/` demo is ONE reference wiring, not the recommended production stack.

---

## The 6 design sections (approved in brainstorming session 2026-08-22)

Each section was presented in the session, approved, and captured in the artifacts referenced below.

### Section 1 — Repository layout

**Captured in:** [Ticket 012 § Scope](../../tickets/012-implement-standalone-runtime-subproject-a.md).

Adds `writer-app/` (Slim 4 host) + Docker Compose + `docker/` + frontend router/Builder/state additions + `docs/`. Library stays in `writer/` as a separate composer package installed by `writer-app/` via a path repo.

### Section 2 — Coffee-shop schema + sample reports

**Captured in:** [Ticket 012 § Scope § Section 2](../../tickets/012-implement-standalone-runtime-subproject-a.md), [ADR-002](../../09-conventions/decisions/002-sqlite-coffee-shop-toy-domain.md).

- SQLite tables: `categories`, `items`, `staff`, `orders`, `order_items`, `payments`, `template_drafts`
- Deterministic seed (`mt_srand(1)`), ~90 days of activity
- Six sample reports covering all three fill paths (2 via `ReportBuilder`, 3 via `DefinitionFiller`, 1 via custom `ReportFillerInterface` — the kitchen sink Full Menu Book with subreports + splittable text + `ComputedExpression` + `onBand`)

### Section 3 — Slim 4 routes, controllers, wiring

**Captured in:** [Ticket 012 § Scope § Section 3](../../tickets/012-implement-standalone-runtime-subproject-a.md), [ADR-001](../../09-conventions/decisions/001-slim-4-http-layer.md).

- Route table: page shells (Home / Viewer / Builder), reporting APIs (`/api/reports/*`, `/api/preview`, `/api/data-sources`, `/api/formatters`), drafts CRUD (`/api/drafts/*`), health check (`/health`)
- Thin controllers, hand-rolled PSR-11 container in `Container.php` (no autowiring library)
- `ReportRegistry` + `ReportDefinition` + `ParamSpec` for lookups
- `SqliteXProvider` classes implementing `ReportDataSourceInterface`; app-side `DescribableDataSource` interface for the Builder's introspection needs (kept out of the library)
- Tightened Slim error middleware for structured JSON errors (400 InvalidArgument, 422 `ElementExceedsPageException`, 500 unexpected — with debug flag)

### Section 4 — Frontend integration

**Captured in:** [Ticket 012 § Scope § Section 4](../../tickets/012-implement-standalone-runtime-subproject-a.md), [ADR-004](../../09-conventions/decisions/004-hash-based-mini-router.md), [ADR-005](../../09-conventions/decisions/005-json-editor-split-screen-live-preview.md), [ADR-006](../../09-conventions/decisions/006-autocomplete-levels-a-b-in-subproject-a.md).

- Hash-based mini-router (no vue-router)
- `HomeLanding.vue`, `BuilderPage.vue`, `JsonEditor.vue` (CodeMirror 6 wrapper)
- `state/builderState.ts`, `state/routerState.ts`, `state/schemaState.ts`
- Split-screen JSON editor + iframe preview + debounced (400 ms) auto-preview
- Autocomplete Levels A+B: static enum completions (band types, content types, aggregate fns, top-level keys, alignment) + dynamic name completions from `/api/data-sources` and `/api/formatters` + snippet expansions (`detail⇥`, `band⇥`, `column⇥`) + linter for unknown enum values
- Draft persistence to SQLite `template_drafts` via `/api/drafts` CRUD
- Existing `ViewerToolbar.vue` and `ReportCanvas.vue` reused; state migrated to router-derived from `data-report-url` fallback

### Section 5 — Docker Compose + local orchestration

**Captured in:** Section 5 conversation on 2026-08-22 (transcript); [ADR-003](../../09-conventions/decisions/003-docker-compose-ports-and-containers.md).

Full section-5 content re-summarized here since it's not otherwise persisted to disk:

- **Services**: `report-writer-php` (Apache + PHP 7.4 + PDO-SQLite + Composer, ports `:8090:80`, healthcheck on `/health`), `report-writer-vite` (Node 20 alpine, port `:5174:5174`)
- **Volumes**: bind-mount `./:/app` for hot reload; bind-mount `./writer-app/data:/app/writer-app/data` for SQLite persistence; named volume `vite_node_modules` to avoid host/container node_modules overlay
- **Vite dev server**: config updated to add `/api` proxy → `http://report-writer-php`; `build.outDir` → `../writer-app/public/build/`; `base: '/build/'`
- **Prod-vs-dev toggle**: single compose file; `APP_ENV=dev` runs both services; `APP_ENV=prod` + `docker compose up report-writer-php` alone serves built bundle from PHP. Slim `viteAssets($env)` helper picks the right `<script>` tags per env.
- **`.env.example`** shipped; `.env` gitignored; env vars: `APP_ENV`, `APP_DEBUG`, `SQLITE_PATH`
- **Startup order**: Vite `depends_on: report-writer-php: condition: service_healthy` — prevents race on first `/api/*` request
- **Concurrent-sessions safety**: ports and container names chosen to not clash with sibling leagues stack (`:8080`, `:5173`, unnamespaced container names)

**Open questions (resolved with defaults):**
- Apache `.htaccess` (Slim convention) — kept as default
- Apache vs PHP-FPM+Nginx — Apache kept for demo simplicity
- Mailpit sidecar service — skipped (no email need)

### Section 6 — Testing strategy + handoff artifacts (framework-agnostic-aware)

**Captured in:** [Ticket 012 § Scope § Section 6](../../tickets/012-implement-standalone-runtime-subproject-a.md), [ADR-013](../../09-conventions/decisions/013-framework-agnostic-library.md).

Full section-6 content re-summarized:

- **Layer 1 — Library unit tests** (`writer/tests/Unit/`, already exist) — PRIMARY suite. Framework-neutral, portable to any consumer. Grow this.
- **Layer 2 — Slim wiring smoke** (`writer-app/tests/Smoke/`) — **4 tests total** since Slim scaffolding is throwaway: `BootSmokeTest`, `ReportRenderSmokeTest`, `PreviewSmokeTest`, `DraftCrudSmokeTest`. Not a template for consumer test suites.
- **Layer 3 — Snapshot tests** (`writer-app/tests/Snapshot/`) — one per sample report against `coffee-shop-mini.sql` fixture. Framework-neutral assertions. Portable value: assert `render(filler, params) === expected_html`.
- **Fixtures**: `writer-app/tests/Fixtures/coffee-shop-mini.sql` (~20 rows for tests) is separate from `writer-app/database/seed.php` (~2500 rows for demo). Never touch the demo DB file from tests.
- **Snapshot helper**: hand-rolled `assertReportSnapshot()` (~40 lines), no phpunit-snapshots dependency; `UPDATE_SNAPSHOTS=1` regenerates.
- **Determinism test**: seeds twice, asserts byte-identical output.
- **In-memory SQLite** per test class; fresh fixture load in `setUp()`.
- **No CI shipped** in Sub-project A (add `.github/workflows/test.yml` later if needed).
- **No frontend testing** in Sub-project A (Vitest / Playwright deferred).
- **Handoff runbook** `docs/handoff/adoption.md` — 3 sections (Symfony 5.x/7, Laravel 10+, plain PHP), one wiring example each, per [ADR-013](../../09-conventions/decisions/013-framework-agnostic-library.md).
- **"Done" checklist**: 6 reports render, builder works end-to-end, `docker compose up` cold under 90s, tests pass across all 3 layers, snapshots green, docs written per docs-after-implementation, handoff runbook exists, tickets 001-011 resolved or explicitly deferred.

---

## Full artifact index

### Architectural Decision Records (docs/09-conventions/decisions/)

| ADR | Title |
|---|---|
| [001](../../09-conventions/decisions/001-slim-4-http-layer.md) | Slim 4 as the standalone runtime's HTTP layer |
| [002](../../09-conventions/decisions/002-sqlite-coffee-shop-toy-domain.md) | SQLite + coffee-shop toy domain for the standalone runtime |
| [003](../../09-conventions/decisions/003-docker-compose-ports-and-containers.md) | Docker Compose, non-conflicting ports, namespaced container names |
| [004](../../09-conventions/decisions/004-hash-based-mini-router.md) | Hash-based mini-router instead of vue-router |
| [005](../../09-conventions/decisions/005-json-editor-split-screen-live-preview.md) | Split-screen JSON editor with debounced live preview + CodeMirror 6 |
| [006](../../09-conventions/decisions/006-autocomplete-levels-a-b-in-subproject-a.md) | Autocomplete Levels A+B in Sub-project A |
| [007](../../09-conventions/decisions/007-imports-over-directory-discovery-for-scripts.md) | Imports over directory-discovery for user scripts (future) |
| [008](../../09-conventions/decisions/008-frontend-scripting-restricted-context.md) | Frontend scripting with restricted-context capability grant (future) |
| [009](../../09-conventions/decisions/009-library-rename-to-edgecase.md) | Rename library from `foreup/reporting` to `edgecase123/report-writer` |
| [010](../../09-conventions/decisions/010-sub-project-decomposition-a-then-b.md) | Sub-project split: A (runtime + JSON builder) → B (structured form builder later) |
| [011](../../09-conventions/decisions/011-docs-after-implementation.md) | Documentation follows implementation, not ahead of it |
| [012](../../09-conventions/decisions/012-github-issues-optional-local-tickets-first.md) | Local ticket files first, GitHub issues on promotion |
| [013](../../09-conventions/decisions/013-framework-agnostic-library.md) | Library is framework-agnostic; `writer-app/` + Docker + npm are dev/test/demo scaffolding only |

### Architecture specs (docs/architecture/) — target v1 + future direction

| Spec | Status |
|---|---|
| [fill-to-layout-schema.md](../../architecture/fill-to-layout-schema.md) | Normative target for v1 layout engine — mirrored from upstream |
| [layout-algorithm-spec.md](../../architecture/layout-algorithm-spec.md) | Normative target for v1 layout engine — mirrored from upstream |
| [test-cases-future.md](../../architecture/test-cases-future.md) | Canonical v1 test cases — mirrored from upstream |
| [user-scripting.md](../../architecture/user-scripting.md) | Hard future requirement: PHP/TS scripting in band hooks and computed content with sandboxing |

### Tickets from the 2026-08-22 audit pass (docs/tickets/)

| # | Title | Priority |
|---|---|---|
| [001](../../tickets/001-aggregate-math-dry-consolidation.md) | Consolidate aggregate math | High |
| [002](../../tickets/002-delete-dead-code-definition-namespace.md) | Delete dead code (`Interfaces/DataProviderInterface` + `Definition/*`) | Medium |
| [003](../../tickets/003-reportbuilder-element-loop-refactor.md) | Refactor `ReportBuilder`'s 4-way element loop | Low |
| [004](../../tickets/004-definitionfiller-band-builder-refactor.md) | Collapse `DefinitionFiller`'s 3-way band builder | Low |
| [005](../../tickets/005-expression-formatter-block-extraction.md) | Extract shared Expression formatter block | Low |
| [006](../../tickets/006-definitionfiller-onband-immutability.md) | Fix `DefinitionFiller::onBand` fluent-mutation | Medium |
| [007](../../tickets/007-zoom-transform-origin-scroll-bug.md) | Fix zoom scroll bug at >100% | Medium |
| [008](../../tickets/008-add-vue-tsc-typecheck-step.md) | Add `vue-tsc --noEmit` to `npm run build` | Medium |
| [009](../../tickets/009-verify-page-margin-print-rule.md) | Verify or add `@page { margin: 0 }` | Low |
| [010](../../tickets/010-library-rename-to-edgecase.md) | Library rename to `edgecase123/report-writer` | Medium |
| [011](../../tickets/011-zoom-preset-placeholder-behavior.md) | Zoom preset placeholder UX | Low |
| [012](../../tickets/012-implement-standalone-runtime-subproject-a.md) | Implement standalone runtime (Sub-project A) | Epic — THIS SPEC |
| [013](../../tickets/013-brainstorm-structured-form-builder-subproject-b.md) | Brainstorm Sub-project B | Epic — deferred |
| [014](../../tickets/014-implement-v1-layout-engine.md) | Implement v1 layout engine | Epic — deferred |
| [015](../../tickets/015-implement-user-scripting.md) | Implement user scripting | Epic — deferred |

### Fixes already landed in the 2026-08-22 audit pass

See `docs/tickets/README.md § Fixed in the same session` for the full list. Highlights:

- Extracted `writer/src/Instance/Grouping.php` with `byField()`; updated both call sites in `ReportBuilder` + `DefinitionFiller`
- Frontend safety fixes: DOMParser replaces regex HTML parsing, type-safe error catch, empty vs error state split, aria-live regions, retry button, focus-visible ring, `role="toolbar"`, `<main>` landmark, print-hide loading/error, TypeScript version fix, hard-coded URL removed, mount-element diagnostic

---

## Prerequisites before implementation begins

1. **Ticket 010 (library rename)** should land first — avoids re-renaming during Sub-project A build. See [Ticket 010](../../tickets/010-library-rename-to-edgecase.md).
2. **Ticket 002 (dead code deletion)** should ideally land in the same PR as 010 so the renamed library ships lean.
3. **Ticket 001 (aggregate math consolidation)** is high-priority and independent; can land before or during Sub-project A.

Everything else in tickets 003–011 can land opportunistically during Sub-project A implementation.

---

## Estimate

Rough single-developer estimate for Sub-project A implementation, not including bug-fix cycles or design iteration: **3–6 weeks**. The `writing-plans` skill produces the ordered subticket breakdown from this spec.
