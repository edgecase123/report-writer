# report-writer

A deterministic reporting pipeline with a matching on-screen / print viewer.

Two components, sharing this repo:

- **`writer/`** — `edgecase123/report-writer`, a PHP 7.4 library. Zero framework dependencies. Turns row data + a layout into paginated HTML or JSON.
- **`frontend/`** — `reporting-viewer`, a Vite + Vue 3 + TypeScript app that fetches server-generated report HTML, previews it at variable zoom, and hands off to the browser's print dialog.

The library is framework-agnostic PHP — usable in Symfony 5.x/7, Laravel 10+, or plain PHP (per [ADR-013](docs/09-conventions/decisions/013-framework-agnostic-library.md)). This repo also ships a Vite + Vue 3 viewer plus scaffolding for a Slim 4 + SQLite + Docker Compose demo runtime that lets you run the whole pipeline end-to-end with `docker compose up`. The Slim/Docker parts are reference material only — real consumers bring their own framework, DI container, HTTP layer, and orchestration.

## The pipeline

```
  ReportBuilder (fluent PHP)  ─┐
                                 ├─►  ReportInstance  ─►  LayoutService  ─►  ReportStream  ─►  HtmlRenderer / JsonRenderer
  DefinitionFiller (JSON)     ─┘        (immutable,          (pure; no                (pages[])
                                        all data resolved)   data access)
```

Four stages, one invariant: **Fill never touches layout math, Layout never touches business data.** The seam is `ReportFillerInterface::fill(array): ReportInstance`. Choose which side of that seam any new feature lives on before choosing where to put code.

**Two ways to fill:**

- `ReportBuilder` — fluent immutable PHP builder. Fastest path for hand-coded tabular reports, supports arbitrarily nested grouping.
- `DefinitionFiller` — generic interpreter driven by a JSON `ReportTemplate`. The foundation for the planned Vue-based report builder UI. Supports one level of grouping; reach for `ReportBuilder` when you need nested groups.

Both produce the same `ReportInstance`; downstream stages cannot tell them apart.

Full library documentation, including a step-by-step tutorial, expression types, band callbacks, multiple data sources, and grouped reports, is in [`writer/README.md`](writer/README.md).

## Repository layout

```
report-writer/
├── writer/                    # PHP 7.4 library (composer package edgecase123/report-writer)
│   ├── src/
│   │   ├── Builder/           # ReportBuilder + Column (fluent immutable API)
│   │   ├── Definition/        # ReportDefinition, BandDefinition, ElementDefinition
│   │   ├── Expression/        # ContentExpression + Static/Field/Aggregate/Computed
│   │   ├── Fill/              # DefinitionFiller + BandContext (JSON → ReportInstance)
│   │   ├── Instance/          # Immutable ReportInstance, BandInstance, ElementInstance
│   │   ├── Interfaces/        # ReportFillerInterface, ReportDataSourceInterface
│   │   ├── Layout/            # LayoutService, Flattener, PageConfig (US Letter 612×792pt)
│   │   ├── Registry/          # DataSourceRegistry, FormatterRegistry
│   │   ├── Renderer/          # HtmlRenderer, JsonRenderer, RendererInterface, StyleMap
│   │   ├── Stream/            # Page, PositionedElement, ReportStream
│   │   ├── Template/          # ReportTemplate + TemplateLoader (JSON schema)
│   │   └── ReportingPipeline.php
│   ├── tests/Unit/            # PHPUnit 9.5 suite
│   ├── composer.json
│   ├── phpunit.xml
│   └── README.md              # full library docs + tutorial
├── frontend/                  # Vite + Vue 3 + TypeScript viewer (reporting-viewer)
│   ├── src/
│   │   ├── App.vue            # composition: ViewerToolbar + ReportCanvas
│   │   ├── main.ts            # mounts on #reporting-viewer-app; reads data-report-url
│   │   ├── components/
│   │   │   ├── ViewerToolbar.vue  # zoom controls, print button
│   │   │   └── ReportCanvas.vue   # fetch + v-html injection of server HTML
│   │   └── state/
│   │       └── viewerState.ts # module-level refs + action functions (zoomIn, zoomTo, etc.)
│   ├── index.html
│   ├── package.json
│   ├── tsconfig.json
│   └── vite.config.js
├── CLAUDE.md                  # guidance for Claude Code sessions
└── .claude/                   # project-specific agents and skills
    ├── agents/
    │   ├── dry-solid-reviewer.md
    │   ├── frontend-designer.md
    │   └── security-scanner.md
    └── skills/
        └── local-context/     # per-task working memory
```

## Getting started

### Library (`writer/`)

```bash
cd writer
composer install
vendor/bin/phpunit                        # full suite
vendor/bin/phpunit tests/Unit/Fill        # one directory
vendor/bin/phpunit --filter GroupBy       # by test name
```

Requires PHP 7.4+. No database, no framework — the test suite runs entirely in memory.

### Viewer (`frontend/`)

```bash
cd frontend
npm install
npm run dev       # Vite dev server with HMR
npm run build     # emits to ../../js/dist/reporting-viewer/
npm run preview
```

Note: the build output path (`../../js/dist/reporting-viewer/`) and public base URL (`/js/dist/reporting-viewer/`) in `vite.config.js` are relative to the **outer** foreUP application checkout, not this repo. A `npm run build` from this repo alone will write files outside the repo tree — expected, as the viewer is designed to be built into the outer app's public assets.

The viewer mounts on any element with id `reporting-viewer-app` and reads the report URL from a `data-report-url` attribute:

```html
<div id="reporting-viewer-app" data-report-url="/api/report/foo?courseId=22537"></div>
<script type="module" src="/js/dist/reporting-viewer/reporting-viewer.js"></script>
```

## Usage sketch

The library is agnostic to how it's wired into a host application. In the outer foreUP app, a typical filler looks like this — full walkthrough (data contract → provider → filler → controller → permissions) is in [`writer/README.md`](writer/README.md#tutorial-your-first-report).

```php
use ReportWriter\Builder\Column;
use ReportWriter\Builder\ReportBuilder;
use ReportWriter\Registry\FormatterRegistry;

$currency = FormatterRegistry::defaults()->get('currency');

$instance = ReportBuilder::create('daily-transactions')
    ->title("Daily Transactions: {$startDate} – {$endDate}")
    ->columns([
        Column::make('transaction_date', 'Date',        0,   120),
        Column::make('description',      'Description', 130, 280),
        Column::make('amount',           'Amount',      490, 82)
            ->sum()
            ->alignRight()
            ->format($currency),
    ])
    ->rows($rows)
    ->build();
```

The `$instance` then flows through `LayoutService` → `ReportStream` → `HtmlRenderer` (or `JsonRenderer`).

## Design invariants

- **Fill / Layout separation.** Fill code never references `PageConfig`, cursor arithmetic, or `PositionedElement`. Layout code never references data providers, `EvalContext`, or `$params`. This is the load-bearing invariant of the pipeline.
- **Immutability at the boundaries.** `ReportInstance`, `BandInstance`, `ElementInstance`, `ReportStream`, `Page`, `PositionedElement` are constructed and never mutated. `ReportBuilder` and its `Column` follow fluent-clone-on-write.
- **Pure renderers.** `HtmlRenderer` and `JsonRenderer` consume a `ReportStream` and produce output. They never call back into Fill or Layout, never hit a data source, never mutate.
- **Points, not pixels.** Coordinates are in points (72 pt = 1 in). Origin top-left, y increases downward. Default page is US Letter (612 × 792 pt). The viewer's zoom control scales for on-screen review; `@media print` removes the transform so paper output is 1:1.

## What lives elsewhere

- Symfony controllers, route definitions, and `permissions.yaml` entries — in the outer foreUP application (`api_rest/src/Controller/Reporting/`).
- DBAL / Doctrine data providers implementing `ReportDataSourceInterface` — in the outer application (`api_rest/rest/models/services/Reporting/Providers/`).
- DI wiring binding `ReportFillerInterface` implementations, formatters, and data-source aliases — in `config/services.php` in the outer application.
- Session / authentication (level 3+ session cookie is required to view reports through the outer app).

## License

Proprietary — internal foreUP tooling. See `writer/composer.json` for authorship.
