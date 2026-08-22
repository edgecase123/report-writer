# TICKET-012: Implement standalone runtime (Sub-project A) per approved design

**Priority:** Epic — highest-level active initiative
**Source:** session-design (2026-08-22) — brainstorming output
**Status:** Blocked on remaining design Sections 5–6, then produces an implementation plan
**Scope:** Whole `writer-app/` directory (new), `frontend/` additions (router + Builder page + CodeMirror + state), `docker-compose.yml`, `docker/`, updates to `writer/` (via [Ticket 010](010-library-rename-to-edgecase.md))

## Problem

report-writer today ships as (a) a PHP library and (b) a Vue viewer designed to be embedded in an outer host application. There is no standalone way to run it end-to-end. Sub-project A adds a Slim 4 host app + SQLite coffee-shop database + Docker Compose orchestration + split-screen JSON builder UI so the whole thing works on `docker compose up` with no outer-app dependency.

**Framing per [ADR-013](../09-conventions/decisions/013-framework-agnostic-library.md):** the library (`writer/`) is framework-agnostic and portable to Symfony 5.x/7, Laravel 10+, or plain PHP. Everything Sub-project A adds under `writer-app/`, `docker/`, `docker-compose.yml`, and `frontend/package.json`'s build wiring is **dev/test/demo scaffolding only**. None of it is prescriptive for consumers. Consumer projects bring their own framework, DI container, HTTP layer, DB access, and orchestration. The `writer-app/` demo exists as ONE way to wire the library up, purely to prove it works end-to-end and to serve as reference material.

## Scope (from approved design sections 1–4)

**Section 1 — Repository layout:**
- New `writer-app/` (Slim 4 host: controllers, DataSource providers, Container.php wiring, JSON template repository, coffee-shop schema, seed)
- Frontend gains: `router.ts` (hash-based), `HomeLanding.vue`, `BuilderPage.vue`, `JsonEditor.vue`, `state/builderState.ts`, `state/schemaState.ts`, `state/routerState.ts`, `editor/completions.ts`, `editor/snippets.ts`
- `docker/`, `docker-compose.yml`, updates to `docs/`

**Section 2 — Coffee-shop schema + sample reports:**
- SQLite tables: `categories`, `items`, `staff`, `orders`, `order_items`, `payments`, `template_drafts`
- Deterministic seed (~90 days of fake POS activity, fixed `mt_srand`)
- 6 sample reports:
  - Daily Sales (`ReportBuilder`)
  - Sales by Category (`DefinitionFiller` JSON)
  - Sales by Category → Item (`ReportBuilder`, nested groups)
  - Open Tabs (`DefinitionFiller` JSON)
  - Register Close (`DefinitionFiller` JSON, multi data-source)
  - Full Menu Book — kitchen sink (custom `ReportFillerInterface`, subreports + splittable text + ComputedExpression + onBand)

**Section 3 — Slim 4 routes + wiring:**
- Routes: `/`, `/viewer`, `/builder`, `/api/reports`, `/api/reports/{id}`, `/api/preview`, `/api/data-sources`, `/api/formatters`, `/api/drafts` (CRUD)
- Controllers thin; hand-rolled PSR-11 container in `Container.php`
- `ReportRegistry` + `ReportDefinition` + `ParamSpec`
- SQLite `DescribableDataSource` providers (name, label, rowSchema)
- Slim error middleware tightened to structured JSON

**Section 4 — Frontend integration:**
- Hash-based mini-router (no vue-router)
- Split-screen JSON editor + iframe preview + debounced 400ms auto-preview
- CodeMirror 6 + Levels A+B autocomplete (static enums + dynamic name completions from `/api/data-sources` + `/api/formatters`)
- Snippet expansions (`detail⇥`, `band⇥`, `column⇥`)
- Linter for unknown enum values
- Draft persistence to SQLite `template_drafts` via `/api/drafts`

**Section 5 — Docker Compose (approved):**
- Services: `report-writer-php` (Apache + PHP 7.4 + PDO-SQLite + Composer), `report-writer-vite` (Node 20 alpine)
- Ports: `:8090` web, `:5174` Vite (non-conflicting with sibling leagues stack)
- Volumes: source bind-mount, `writer-app/data/` for SQLite persistence, named volume `vite_node_modules`
- First-run workflow: `docker compose up -d --build` → `composer install` (twice — writer and writer-app) → `php bin/console db:seed` → open browser
- Vite `/api` proxy → `http://report-writer-php`; production build outputs to `writer-app/public/build/`
- Prod-vs-dev toggle via `APP_ENV` env var + Slim `viteAssets()` helper choosing `<script>` tags

**Section 6 — Testing (approved, framework-agnostic-aware):**
- Layer 1: library unit tests (`writer/tests/Unit/`, already exist) — PRIMARY suite, portable across all consumers
- Layer 2: Slim wiring smoke — **only 4 tests total** (`BootSmokeTest`, `ReportRenderSmokeTest`, `PreviewSmokeTest`, `DraftCrudSmokeTest`) since Slim scaffolding is throwaway from any consumer's perspective
- Layer 3: snapshot tests — one per sample report against `coffee-shop-mini.sql` fixture, framework-neutral assertions (`render(filler, params) === expected_html`), portable to any consumer
- Fixtures: `writer-app/tests/Fixtures/coffee-shop-mini.sql` (~20 rows for tests) separate from `writer-app/database/seed.php` (~2500 rows for demo)
- Determinism test: seed twice, assert byte-identical output
- No CI in Sub-project A; can add `.github/workflows/test.yml` later
- No frontend tests (Vitest/Playwright) in Sub-project A; defer

## Blockers

- **[Ticket 010 (library rename)](010-library-rename-to-edgecase.md)** should ideally land first so `writer-app/` depends on `edgecase123/report-writer` from day one (avoids a second rename pass)

## Deliverable

Design is complete (all 6 sections approved 2026-08-22). The `writing-plans` skill produces the implementation plan that breaks this epic into subtickets. Rough estimate: 30–50 subtickets, single-developer 3–6 week effort.

## Handoff artifact requirement

Per [ADR-013](../09-conventions/decisions/013-framework-agnostic-library.md), `docs/handoff/adoption.md` becomes the load-bearing handoff artifact. Three subsections, one per framework: **Symfony 5.x/7**, **Laravel 10+**, **plain PHP**. Each shows the concrete wiring pattern (service definitions, controllers, DB access, DI). Written after the demo implementation lands, per [ADR-011](../09-conventions/decisions/011-docs-after-implementation.md).

## Notes

- Design captured in this session's conversation and (partially) in `docs/architecture/user-scripting.md` (future extension). ADRs to be written for load-bearing decisions ([Ticket 015 companion](../))
- The `frontend-designer` agent's role definition is calibrated for this project's viewer; use it (or the `general-purpose` agent invoking it) when designing new UI surfaces within this epic.
