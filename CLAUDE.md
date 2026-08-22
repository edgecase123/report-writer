# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository layout

Two independent components sit side by side:

- `writer/` — `edgecase123/report-writer`, a PHP 7.4 library (zero framework deps). PSR-4 namespace `ReportWriter\` → `writer/src/`.
- `frontend/` — `reporting-viewer`, a standalone Vite + Vue 3 + TypeScript viewer app that fetches and displays server-rendered report HTML.

The library is framework-agnostic (per [ADR-013](docs/09-conventions/decisions/013-framework-agnostic-library.md)). Consumers wire it into their own stack: Symfony 5.x/7 controllers + `config/services.yaml`, Laravel 10+ service provider + controllers, or plain PHP. The `writer-app/` demo (Slim 4, planned in Sub-project A) is reference material, not a production recommendation.

## Common commands

### PHP library (`writer/`)

```bash
cd writer
composer install
vendor/bin/phpunit                        # full suite
vendor/bin/phpunit tests/Unit/Fill        # one directory
vendor/bin/phpunit --filter TestName      # one test
```

Test bootstrap is `vendor/autoload.php` (see `writer/phpunit.xml`). PHPUnit 9.5, PHP ≥ 7.4.

### Frontend viewer (`frontend/`)

```bash
cd frontend
npm install
npm run dev       # Vite dev server
npm run build     # emits to ../../js/dist/reporting-viewer/ (path assumes outer app checkout)
npm run preview
```

The Vite `build.outDir` is `../../js/dist/reporting-viewer/` and `base` is `/js/dist/reporting-viewer/` — both are relative to the outer application, so a build run from this repo alone will write outside the tree.

## Architecture — the pipeline

The library implements a deterministic four-stage pipeline: **Fill → Layout → Stream → Render**.

```
  ReportBuilder (fluent PHP)  ─┐
                                 ├─►  ReportInstance  ─►  LayoutService  ─►  ReportStream  ─►  HtmlRenderer / JsonRenderer
  DefinitionFiller (JSON)     ─┘        (immutable,          (pure; no                (pages[])
                                        all data resolved)   data access)
```

The **key invariant** — enforced by module boundaries, not by lint — is that **Layout never touches business data and Fill never touches layout math**. `ReportFillerInterface::fill(array): ReportInstance` is the seam. When adding a new feature, decide which side of that seam it lives on before choosing where to put code.

### Two fillers, same output

- `Builder/ReportBuilder` — fluent immutable builder (`->title()`, `->columns()`, `->rows()`, `->groupBy(...)`, `->build()`). Every method `clone`s and returns; do not mutate in place. Handles arbitrary nested grouping.
- `Fill/DefinitionFiller` — generic interpreter driven by a JSON `ReportTemplate` (loaded via `Template/TemplateLoader`). Wires rows from a `Registry/DataSourceRegistry` (multiple named sources, each fetched at most once per `fill()`). **DefinitionFiller supports only one level of grouping** — reach for `ReportBuilder` when you need nested groups.

Both produce a `Instance/ReportInstance`; downstream stages cannot tell them apart.

### Content expressions

Every cell's contents are a `Expression/ContentExpression` evaluated against `Expression/EvalContext` (row, aggregateRows, params):

| Type | When |
|---|---|
| `StaticExpression` | literal string |
| `FieldExpression` | value from current row (default for detail cells) |
| `AggregateExpression` | `sum` / `avg` / `min` / `max` / `count` over rows in scope |
| `ComputedExpression` | arbitrary callable receiving `EvalContext` |

`Column::sum()` / `->avg()` / etc. shortcuts install an `AggregateExpression` on both the footer and summary expressions. In JSON definitions the equivalent is `content: { type: "aggregate", fn: "sum", field: "..." }`.

### Layout

`Layout/LayoutService::layout(ReportInstance)` flattens the report (inlining subreports via `Flattener`), then walks bands top-to-bottom placing them into fixed-size pages. Coordinate system: top-left origin, y increases downward, units are **points** (72 pt = 1 in). Default page is US Letter (612 × 792 pt, `Layout/PageConfig`). Band height = max element height in the band; when a band does not fit, splittable text elements (single-element bands whose content reports `isSplittable()`) are split across a page boundary and everything else forces a new page.

### Rendering

`Renderer/HtmlRenderer` and `Renderer/JsonRenderer` both implement `RendererInterface` and consume the `Stream/ReportStream` (`Page[]` of `PositionedElement`). Renderers are pure — never call back into fill or layout.

### Extension points to know about

- `DefinitionFiller::onBand($bandId, callable)` — post-build hook. Return `null` to suppress the band, or a (possibly modified) `BandInstance` to include it. Callbacks chain.
- Subreports — a band containing a `SubreportContent` element is replaced by the referenced subreport's flattened bands at layout time (`Flattener`). Recursion is detected and throws `RecursiveSubreportException`.

## Frontend

Trivial shell (`main.ts` → `App.vue` → `ViewerToolbar` + `ReportCanvas`) with a single reactive module `state/viewerState.ts`. `ReportCanvas.vue` fetches the report URL (passed in via the `data-report-url` attribute on the mount element), extracts `<style>` blocks and body content from the returned HTML document, and injects them via `v-html`. The injected HTML is trusted because the fetching endpoint is authenticated — do not point the viewer at untrusted origins.
