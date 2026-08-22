---
title: Architecture Overview
updated: 2026-08-22
covers:
  - writer/src/ReportingPipeline.php
  - writer/src/Layout/LayoutService.php
  - writer/src/Fill/DefinitionFiller.php
  - writer/src/Builder/ReportBuilder.php
  - writer/src/Renderer/HtmlRenderer.php
  - writer/src/Renderer/JsonRenderer.php
  - frontend/src/main.ts
  - frontend/src/App.vue
---

# Architecture Overview

Three deployable units — a pure PHP library, a Slim 4 host app that consumes it, and a Vue 3 frontend that talks to the host over HTTP. The library implements a four-stage pipeline built around a single load-bearing invariant: **Fill never touches layout math; Layout never touches business data**.

---

## Tech stack

| Layer | Technology |
|---|---|
| Language (library) | PHP 7.4+ |
| Language (host app) | PHP 7.4+ |
| Language (frontend) | TypeScript 6 + Vue 3 |
| Web framework (host) | Slim 4 |
| Framework (library) | None. Zero framework dependencies by design. |
| Database (demo) | SQLite 3 |
| Frontend build | Vite 5 |
| Frontend editor | CodeMirror 6 (JSON) |
| Testing | PHPUnit 9.5 |
| Local orchestration | Docker Compose |
| Web server (dev) | Apache in the PHP container (`report-writer-php`), Vite dev server on the side (`report-writer-vite`) |

The library targeting PHP 7.4 is deliberate — it needs to run inside consumer applications that may not have moved to PHP 8 yet.

---

## The three components

```
report-writer/
├── writer/          edgecase123/report-writer library — pure PHP, zero framework deps
├── writer-app/      Slim 4 host application — routes, controllers, SQLite plumbing
└── frontend/        Vue 3 viewer + builder UI
```

**Library (`writer/`).** The pipeline. Namespace `ReportWriter\`. Publishable to Packagist independently. Consumers of the library provide (a) data sources that implement `ReportDataSourceInterface`, (b) request handling (a Symfony controller, a Slim route, a CLI command — anything). What consumers get back is a rendered string.

**Host app (`writer-app/`).** A working demonstration of the library wired into a real application. Slim 4 routes call fillers, fillers call SQLite-backed data sources, results render to HTML or JSON. This is what you'd model your own integration on.

**Frontend (`frontend/`).** Two Vue surfaces — a **viewer** that displays server-rendered report HTML at variable zoom (and hands off cleanly to the browser's print dialog), and a **builder** that lets you edit a JSON template on the left with a live-updating preview on the right.

The library depends on nothing. The host app depends on the library (via composer path repo). The frontend depends on nothing at build time and talks to the host app at runtime over HTTP.

---

## The pipeline (the heart of the library)

```
       Fill                Layout               Stream              Render
        │                    │                    │                   │
        ▼                    ▼                    ▼                   ▼
  ReportInstance   ─►   LayoutService   ─►   ReportStream   ─►   HtmlRenderer
   (immutable,           (pure geometry;      (pages[] of         JsonRenderer
   all data resolved)    no data access)      PositionedElement)
```

Each arrow is a strict handoff. What flows across each arrow is immutable data, and each stage is unaware of the others' internals.

**Stage 1 — Fill.** Turns runtime parameters (dates, IDs, filters) into a `ReportInstance` — an immutable tree of bands and elements where every value has already been formatted to a string. Three fill paths ship: `ReportBuilder` (fluent PHP), `DefinitionFiller` (JSON template), and custom classes implementing `ReportFillerInterface` directly.

**Stage 2 — Layout.** Walks the bands top-to-bottom, tracks a cursor, places each band at an absolute `(x, y)` in points against the page (default US Letter, 612 × 792 pt). Splits multi-line text at page boundaries. Throws if an element doesn't fit on a single page. Produces a `ReportStream`. *This is the current shipped behavior — the v1 target for this stage is much richer (keep rules, forced breaks, page headers/footers, subreport-as-container pagination with preserved parent linkage, stretchable elements). See [Architecture Specifications](../architecture/) for the target.*

**Stage 3 — Stream.** Not really a stage — a data structure. `ReportStream` is `Page[]`; each `Page` holds `PositionedElement[]`. This is the handoff format renderers consume. No processing happens here; it exists so renderers don't have to understand layout.

**Stage 4 — Render.** Emits HTML (for browsers and printers) or JSON (for downstream consumers). Two renderers ship: `HtmlRenderer` and `JsonRenderer`. Both are pure — they never call back into fill or layout, never touch the database.

**The load-bearing invariant:** Fill never references `PageConfig`, cursor arithmetic, or `PositionedElement`. Layout never references data providers, `EvalContext`, or `$params`. The seam is `ReportFillerInterface::fill(array $params): ReportInstance`. When you add a feature, you decide which side of the seam it lives on before writing a line.

This invariant is also the governing principle of the v1 target spec at [`docs/architecture/`](../architecture/): *"Fill decides what exists; layout decides where it goes and how it splits."* Same rule.

Full detail on each stage in [Pipeline → Overview](../04-pipeline/overview.md) (planned).

---

## Local system diagram

```
                     ┌─────────────────────────────────────────┐
                     │           Docker Compose stack           │
                     │                                          │
                     │   ┌─────────────────┐  ┌──────────────┐  │
   Browser  ────►    │   │ report-writer-  │  │ report-      │  │
   :8090             │   │ php  (Slim 4)   │  │ writer-vite  │  │
                     │   │                 │  │ (Vite dev)   │  │
                     │   │  writer-app/    │  │  frontend/   │  │
                     │   │      │          │  │              │  │
                     │   │      ▼          │  │  Proxies     │  │
                     │   │  edgecase/      │  │  /api/*  ──► │  │
                     │   │  report-writer  │  │  php:8090    │  │
                     │   │  (library)      │  │              │  │
                     │   │      │          │  └──────────────┘  │
                     │   │      ▼          │        :5174       │
                     │   │  data/*.sqlite  │                    │
                     │   └─────────────────┘                    │
                     └──────────────────────────────────────────┘

   Browser (dev)  ──►  :5174   Vite dev server (HMR), which proxies /api to PHP
   Browser (prod) ──►  :8090   PHP serves the Vite-built bundle from public/
```

The frontend is served by Vite in dev mode with an `/api` proxy to the PHP container. In production (`npm run build`), the bundle is emitted into `writer-app/public/build/` and served by PHP directly, so only one origin is in play.

Port choices deliberately avoid conflicts with the sibling leagues stack on `:8080` / `:5173`.

## Request lifecycle (rendering a report)

1. Browser: `GET /api/reports/daily-sales?date=2026-08-22`
2. Slim routes the request to `ReportController::render('daily-sales')`.
3. Controller pulls the report definition from `ReportRegistry`, validates query params against the definition's param schema (400 JSON if invalid).
4. Controller instantiates the report's filler (e.g. `DailySalesFiller`), which is constructed with the `SqliteDailySalesProvider` data source.
5. `$filler->fill($params)` returns a fully-resolved `ReportInstance`.
6. `$layoutService->layout($instance)` returns a `ReportStream`.
7. Based on `?format=`, the controller picks `HtmlRenderer` (default) or `JsonRenderer`.
8. Response returns with the renderer's content type.

The library's contribution here is steps 5–7. Steps 1–4 and 8 are host-app concerns and would look completely different in any other framework — but the library doesn't care.

## Frontend architecture

```
frontend/src/
  main.ts               Router entry — mounts the right Vue app based on the URL hash
  App.vue               Root component
  router.ts             ~20-line hash-based router (no vue-router dependency)
  components/
    HomeLanding.vue     Lists available reports (planned)
    ViewerToolbar.vue   Zoom + print controls
    ReportCanvas.vue    Fetches server HTML, injects via v-html, scales for zoom
    BuilderPage.vue     Split-screen JSON editor + preview (planned)
    JsonEditor.vue      CodeMirror 6 wrapper (planned)
  state/
    viewerState.ts      Module-scope reactive refs + action functions
    builderState.ts     Template drafts, preview state, error surface (planned)
```

State lives in module-scope `ref()`s exported from `state/` files. Components import and use directly. No Pinia, no vue-router, no store abstraction — the pattern is small enough that the module-scope approach reads more directly.

The **viewer** trusts the HTML it receives because the server escapes every value via `HtmlRenderer::htmlspecialchars()` before emission. That trust boundary is enforced by an inline comment in `ReportCanvas.vue` and is the only reason `v-html` is safe. See [Concepts → What is a report? § HTML safety](../03-concepts/what-is-a-report.md) (planned).

---

## Source files

| File | Role |
|---|---|
| `writer/src/ReportingPipeline.php` | Ties Fill → Layout together as one call |
| `writer/src/Layout/LayoutService.php` | The pagination + placement engine |
| `writer/src/Fill/DefinitionFiller.php` | JSON template → `ReportInstance` |
| `writer/src/Builder/ReportBuilder.php` | Fluent PHP builder → `ReportInstance` |
| `writer/src/Renderer/HtmlRenderer.php` | `ReportStream` → HTML string |
| `writer/src/Renderer/JsonRenderer.php` | `ReportStream` → JSON string |
| `frontend/src/main.ts` | Frontend entry point |
| `frontend/src/App.vue` | Root Vue component |

## Related docs

- [Product Overview](product.md) — what report-writer is and who uses it
- [Glossary](glossary.md) — every term used above, precisely defined
- [Concepts → What is a report?](../03-concepts/what-is-a-report.md) — reporting fundamentals
- [Pipeline → Overview](../04-pipeline/overview.md) — *(planned)* deep dive on each of the four stages
