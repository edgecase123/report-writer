# foreup/reporting

Deterministic reporting pipeline: **Fill → Layout → Stream → Render**.

Fill resolves all business data into an immutable `ReportInstance`. Layout paginates it into absolutely-positioned elements with no data access. Renderers (HTML, JSON) consume the stream — layout is render-agnostic.

There are two ways to produce a `ReportInstance`:

- **`ReportBuilder`** — fluent PHP API; fastest path for hand-coded tabular reports.
- **`DefinitionFiller` + JSON** — generic interpreter driven by a JSON definition file; the foundation for the Vue-based report builder UI planned.

Both produce the same `ReportInstance` and are handled identically by the layout and render phases.

---

## Requirements

- Docker environment running (`up` alias or `docker compose up -d` from repo root)
- PHP 7.4 inside the `php-apache` container
- Node 20+ inside the container (via nvm, sourced from `~/.bashrc`)

All commands below run **inside the container** unless otherwise noted.

```bash
docker exec -it php-apache bash
source ~/.bashrc   # loads nvm / node
```

Or use the `docker-bash` alias if installed.

---

## Architecture

```
   ReportBuilder (fluent PHP)
          │
          │                    JSON Definition File
          │                           │
          │                    DefinitionFiller  ← DataSourceRegistry
          │                           │
          └──────────────────────────►┤
                                      ▼
                               ReportInstance        ← immutable; all data resolved
                                      │
                               LayoutService         ← pure; no data access
                                      │
                               ReportStream (pages[])
                                      │
                          HtmlRenderer / JsonRenderer
```

**Key invariant:** Layout never touches business data. Fill never touches layout math. The boundary is `ReportFillerInterface`.

### Package location

```
api_rest/packages/foreup-reporting/   ← core pipeline (zero framework deps)
api_rest/rest/models/services/Reporting/
  Contracts/     ← *DataInterface — typed DB contracts
  Providers/     ← Dbal* — all Doctrine/framework coupling
  Fillers/       ← concrete fillers; depend on Contracts only
api_rest/src/Controller/Reporting/    ← Symfony controllers
frontend/reporting-viewer/            ← standalone Vue 3 + TypeScript viewer
```

---

## Running the Tests

### Package tests (pipeline unit tests)

```bash
cd /var/www/html/api_rest
php bin/phpunit packages/foreup-reporting/tests
# Expected: 109 tests, 216 assertions, OK
```

### Filler tests

```bash
cd /var/www/html/api_rest
php bin/phpunit tests/unit/models/services/Reporting/Fillers/
# Expected: 34 tests, 71 assertions, OK
```

Filler tests mock `*DataInterface` directly — no database required.

---

## Viewing Reports

Requires a level 3+ session cookie (log in at `https://development.foreupsoftware.com` first).

### Via the viewer (recommended)

```
https://development.foreupsoftware.com/api_rest/index.php/reporting/viewer?report=unsettled-captures&courseId=22537&days=4&lookbackDays=90
https://development.foreupsoftware.com/api_rest/index.php/reporting/viewer?report=sales-by-category&courseId=22537&startDate=2026-01-01&endDate=2026-05-01
```

The viewer provides zoom controls (50–200%), a preset dropdown, and a Print button. Print output suppresses the browser URL bar via `@page { margin: 0 }`.

### Direct HTML output

```
https://development.foreupsoftware.com/api_rest/index.php/reporting/unsettled-captures?courseId=22537&days=4&lookbackDays=90
https://development.foreupsoftware.com/api_rest/index.php/reporting/sales-by-category?courseId=22537&startDate=2026-01-01&endDate=2026-05-01
```

### Raw JSON stream

Append `&format=json` to any report URL:

```
https://development.foreupsoftware.com/api_rest/index.php/reporting/unsettled-captures?courseId=22537&format=json
```

---

## Building the Viewer

The viewer is a standalone Vite + Vue 3 + TypeScript app. Build output goes to `js/dist/reporting-viewer/`.

```bash
cd /var/www/html/frontend/reporting-viewer
npm install
npm run build
```

Dev mode (hot reload against the local site):

```bash
npm run dev
```

---

## Tutorial: Your First Report

This walks through a complete "Daily Transactions" report — rows from one data source with a grand total — using `ReportBuilder`.

**Steps at a glance:**
1. Define a typed data contract (interface) — keeps DB coupling out of the filler
2. Implement the DBAL provider — all Doctrine code lives here
3. Create the filler — builds the `ReportInstance` via `ReportBuilder`
4. Create the controller — validates params, calls `fill()`, returns `respond()`
5. Add a `permissions.yaml` entry and test in the browser

---

### Step 1 — Define the data contract

Create `rest/models/services/Reporting/Contracts/DailyTransactionsDataInterface.php`:

```php
<?php

namespace foreup\rest\models\services\Reporting\Contracts;

interface DailyTransactionsDataInterface
{
    /**
     * @return array<int, array{transaction_date: string, description: string, amount: float}>
     */
    public function fetchRows(array $params): array;
}
```

This interface is what the filler depends on. All DB coupling stays out of it.

---

### Step 2 — Implement the provider

Create `rest/models/services/Reporting/Providers/DbalDailyTransactionsProvider.php`:

```php
<?php

namespace foreup\rest\models\services\Reporting\Providers;

use Doctrine\DBAL\Connection;
use foreup\rest\models\services\Reporting\Contracts\DailyTransactionsDataInterface;

class DbalDailyTransactionsProvider implements DailyTransactionsDataInterface
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function fetchRows(array $params): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT transaction_date, description, amount
               FROM transactions
              WHERE course_id  = :courseId
                AND transaction_date BETWEEN :startDate AND :endDate
              ORDER BY transaction_date',
            [
                'courseId'  => $params['courseId'],
                'startDate' => $params['startDate'],
                'endDate'   => $params['endDate'],
            ]
        );
    }
}
```

Wire in `config/services.php`:

```php
use foreup\rest\models\services\Reporting\Contracts\DailyTransactionsDataInterface;
use foreup\rest\models\services\Reporting\Providers\DbalDailyTransactionsProvider;

$services->alias(DailyTransactionsDataInterface::class, DbalDailyTransactionsProvider::class);
```

---

### Step 3 — Create the filler

Create `rest/models/services/Reporting/Fillers/DailyTransactionsFiller.php`:

```php
<?php

namespace foreup\rest\models\services\Reporting\Fillers;

use foreup\Reporting\Builder\Column;
use foreup\Reporting\Builder\ReportBuilder;
use foreup\Reporting\Instance\ReportInstance;
use foreup\Reporting\Interfaces\ReportFillerInterface;
use foreup\Reporting\Registry\FormatterRegistry;
use foreup\rest\models\services\Reporting\Contracts\DailyTransactionsDataInterface;

class DailyTransactionsFiller implements ReportFillerInterface
{
    private DailyTransactionsDataInterface $data;

    public function __construct(DailyTransactionsDataInterface $data)
    {
        $this->data = $data;
    }

    public function fill(array $params): ReportInstance
    {
        $rows     = $this->data->fetchRows($params);
        $currency = FormatterRegistry::defaults()->get('currency');

        return ReportBuilder::create('daily-transactions')
            ->title("Daily Transactions: {$params['startDate']} – {$params['endDate']}")
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
    }
}
```

Wire in `config/services.php`:

```php
use foreup\rest\models\services\Reporting\Fillers\DailyTransactionsFiller;

$services->set(DailyTransactionsFiller::class)->autowire();
```

Symfony will autowire `DailyTransactionsDataInterface` → `DbalDailyTransactionsProvider` based on the alias registered in step 2.

---

### Step 4 — Create the controller

Create `src/Controller/Reporting/DailyTransactionsController.php`:

```php
<?php

namespace App\Controller\Reporting;

use foreup\rest\models\services\Reporting\Fillers\DailyTransactionsFiller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DailyTransactionsController extends AbstractReportingController
{
    private DailyTransactionsFiller $filler;

    public function __construct(DailyTransactionsFiller $filler)
    {
        $this->filler = $filler;
    }

    /**
     * @Route("/reporting/daily-transactions", name="reporting_daily_transactions", methods={"GET"})
     */
    public function report(Request $request): Response
    {
        try {
            $instance = $this->filler->fill([
                'courseId'  => $request->query->get('courseId'),
                'startDate' => $request->query->get('startDate'),
                'endDate'   => $request->query->get('endDate'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return new Response($e->getMessage(), 400);
        }

        return $this->respond($instance, $request);
    }
}
```

Add to `config/permissions.yaml`:

```yaml
- { min_level: 3, controller: 'DailyTransactionsController' }
```

> **Important:** The route method must be named (not `__invoke`). Symfony omits `::__invoke` from the `_controller` attribute, which breaks `isAuthorized()` in `LegacyCookieAuthenticator`.

---

### Step 5 — Test it

```
# HTML output
https://development.foreupsoftware.com/api_rest/index.php/reporting/daily-transactions?courseId=22537&startDate=2026-01-01&endDate=2026-05-01

# Via viewer
https://development.foreupsoftware.com/api_rest/index.php/reporting/viewer?report=daily-transactions&courseId=22537&startDate=2026-01-01&endDate=2026-05-01

# Raw JSON stream
https://development.foreupsoftware.com/api_rest/index.php/reporting/daily-transactions?courseId=22537&startDate=2026-01-01&endDate=2026-05-01&format=json
```

---

## Column Content Expressions

Every cell in a report — detail rows, group footers, summaries — contains a **content expression** that is evaluated when the band is built. Four expression types cover all use cases:

| Expression | Purpose | Example |
|---|---|---|
| `StaticExpression($text)` | Literal string, unchanged | `new StaticExpression('Category Total')` |
| `FieldExpression($field)` | Value from the current row | `new FieldExpression('item_name')` (default for all columns) |
| `AggregateExpression($fn, $field)` | Computed over rows in scope | Sum/avg/min/max/count — use `->sum()`, `->avg()`, etc. shortcuts |
| `ComputedExpression($fn)` | Arbitrary callable | `new ComputedExpression(fn($ctx) => count($ctx->aggregateRows) > 0 ? '✓' : '')` |

### Setting column content

- **Detail cells** — controlled by `FieldExpression` (the default); use `->format(callable)` to customize display
- **Group footer cells** — set via `->footerContent(ContentExpression)`; blank if not set
- **Summary cells** — set via `->summaryContent(ContentExpression)`; blank if not set
- **Aggregate shortcuts** — `->sum()`, `->avg()`, etc. set the footer and summary expressions to an `AggregateExpression` automatically

### Example: custom footer logic

```php
$col = Column::make('completed', 'Completed', 0, 100)
    ->footerContent(
        new ComputedExpression(
            fn($ctx) => count($ctx->aggregateRows) > 0 ? 'Yes' : 'No'
        )
    );
```

---

## ReportBuilder Reference

### ReportBuilder API

| Method | Description |
|---|---|
| `ReportBuilder::create($id)` | Start a new builder |
| `->title($text, $height = 30)` | Add a title band |
| `->columns(Column[])` | Define columns (controls headers, detail rows, and aggregates) |
| `->rows($rows)` | Provide data rows as an array of associative arrays |
| `->rowHeight($pt)` | Override the default row height (12 pt) |
| `->groupBy($field, ...)` | Group rows by one or more field keys (outermost first) |
| `->build()` | Return the `ReportInstance` |

### Column API

| Method | Description |
|---|---|
| `Column::make($id, $header, $x, $width)` | Define a column |
| `->sum()` | Aggregate: sum all row values in group footer and summary |
| `->avg()` | Aggregate: arithmetic mean of row values |
| `->min()` | Aggregate: smallest row value |
| `->max()` | Aggregate: largest row value |
| `->count()` | Aggregate: number of rows |
| `->format(callable)` | Callable applied to detail, footer, and summary values for display |
| `->footerContent(ContentExpression)` | Override group-footer cell content (e.g., `new StaticExpression('Category Total')`) |
| `->summaryContent(ContentExpression)` | Override summary cell content (e.g., `new StaticExpression('Grand Total')`) |
| `->alignRight()` / `->alignLeft()` / `->alignCenter()` | Text alignment |
| `->margin($left, $right = 0)` | Horizontal inset within the column cell |

---

## DefinitionFiller: JSON-Driven Reports

`DefinitionFiller` interprets a JSON definition file generically — no report-specific knowledge lives in PHP. The definition file is the complete source of truth for band layout, content types, aggregates, and data source wiring.

This approach is the foundation for the planned Vue-based report builder UI, which will construct and persist these JSON definitions without requiring a code deployment.

**Steps at a glance:**
1. Write a JSON definition file — describes bands, elements, content types, and data source name
2. Create the filler — loads the template, wires `DataSourceRegistry`, delegates to `DefinitionFiller`
3. Controller and `permissions.yaml` are identical to the `ReportBuilder` path — reuse them as-is

---

### The same report as a JSON definition

`rest/models/services/Reporting/Definitions/daily-transactions.json`:

```json
{
  "report_definition_id": "daily-transactions",
  "data_source": "daily_transactions",
  "params": {
    "courseId":  { "type": "int",    "required": true },
    "startDate": { "type": "string", "required": true },
    "endDate":   { "type": "string", "required": true }
  },
  "bands": [
    {
      "id": "title", "type": "title",
      "elements": [
        { "id": "t", "x": 0, "width": 572, "height": 20,
          "content": { "type": "text", "value": "Daily Transactions: {startDate} – {endDate}" } }
      ]
    },
    {
      "id": "col_header", "type": "col-header",
      "elements": [
        { "id": "h_date",   "x": 0,   "width": 120, "height": 14, "content": { "type": "text", "value": "Date" } },
        { "id": "h_desc",   "x": 130, "width": 280, "height": 14, "content": { "type": "text", "value": "Description" } },
        { "id": "h_amount", "x": 490, "width": 82,  "height": 14, "align": "right",
          "content": { "type": "text", "value": "Amount" } }
      ]
    },
    {
      "id": "detail", "type": "detail", "row_spacing": 2,
      "elements": [
        { "id": "date",   "x": 0,   "width": 120, "height": 12, "content": { "type": "field", "field": "transaction_date" } },
        { "id": "desc",   "x": 130, "width": 280, "height": 12, "content": { "type": "field", "field": "description" } },
        { "id": "amount", "x": 490, "width": 82,  "height": 12, "align": "right",
          "content": { "type": "field", "field": "amount", "format": "currency" } }
      ]
    },
    {
      "id": "summary", "type": "summary",
      "elements": [
        { "id": "lbl",   "x": 0,   "width": 480, "height": 14, "content": { "type": "text", "value": "Grand Total" } },
        { "id": "total", "x": 490, "width": 82,  "height": 14, "align": "right",
          "content": { "type": "aggregate", "field": "amount", "fn": "sum", "format": "currency" } }
      ]
    }
  ]
}
```

`{startDate}` / `{endDate}` in `text` content are interpolated from the params passed to `fill()`. `row_spacing: 2` adds 2 pt of vertical gap after each detail row.

### The filler

```php
<?php

namespace foreup\rest\models\services\Reporting\Fillers;

use foreup\Reporting\Fill\DefinitionFiller;
use foreup\Reporting\Instance\ReportInstance;
use foreup\Reporting\Interfaces\ReportFillerInterface;
use foreup\Reporting\Registry\DataSourceRegistry;
use foreup\Reporting\Registry\FormatterRegistry;
use foreup\Reporting\Template\TemplateLoader;
use foreup\rest\models\services\Reporting\Contracts\DailyTransactionsDataInterface;

class DailyTransactionsFiller implements ReportFillerInterface
{
    private DailyTransactionsDataInterface $data;

    public function __construct(DailyTransactionsDataInterface $data)
    {
        $this->data = $data;
    }

    public function fill(array $params): ReportInstance
    {
        $template = (new TemplateLoader())->load(
            __DIR__ . '/../Definitions/daily-transactions.json'
        );

        $registry = new DataSourceRegistry();
        $registry->register('daily_transactions', $this->data);

        return (new DefinitionFiller($template, $registry, FormatterRegistry::defaults()))
            ->fill($params);
    }
}
```

The name passed to `$registry->register()` must match the `data_source` value in the JSON definition. The controller is identical to the `ReportBuilder` version — `fill()` returns a `ReportInstance` either way.

### Band fields

| Field | Type | Default | Description |
|---|---|---|---|
| `id` | string | required | Unique within the definition |
| `type` | string | required | `title`, `col-header`, `detail`, `summary`, `group-header`, `group-footer` |
| `data_source` | string | template default | Override which data source this band fetches from |
| `group_by` | string | — | Field name to group rows by (required on `group-header`) |
| `row_spacing` | float | `0.0` | Extra vertical gap (pt) added after the band |
| `elements` | array | required | Element definitions |

### Element fields

| Field | Type | Default | Description |
|---|---|---|---|
| `id` | string | required | Unique within the band |
| `x` | float | required | Left offset from the usable area origin (pt) |
| `width` | float | required | Element width (pt) |
| `height` | float | required | Element height (pt) |
| `align` | string | `left` | `left`, `right`, `center` |
| `content` | object | required | Content definition (see below) |

### Content types

| Type | Required fields | Optional fields | Description |
|---|---|---|---|
| `text` | `value` | — | Static string; `{param}` tokens are interpolated |
| `field` | `field` | `format` | Value from the current row |
| `aggregate` | `field`, `fn` | `format` | Computed over rows in scope (`sum`, `count`, `avg`, `min`, `max`) |
| `group_value` | — | — | The current group key (only valid in `group-header`/`group-footer`) |

---

## Advanced: Grouped Reports

### ReportBuilder — single level

Pass one field key to `->groupBy()`. Each group automatically gets a **group-footer summary band** containing the aggregate value for every aggregate column. To set labels, add explicit `->footerContent()` and `->summaryContent()` expressions to non-aggregate columns:

```php
ReportBuilder::create('sales-by-category')
    ->title("Sales: {$params['startDate']} – {$params['endDate']}")
    ->columns([
        Column::make('description', 'Description', 0,   490)
            ->footerContent(new StaticExpression('Category Total'))
            ->summaryContent(new StaticExpression('Grand Total')),
        Column::make('amount',      'Amount',       490, 82)
            ->sum()
            ->alignRight()
            ->format($currency),
    ])
    ->rows($rows)
    ->groupBy('category')
    ->build();
```

Band sequence for two categories:

```
col-header
group-header  "Apparel"
  detail      "Hat"          $15.00
  detail      "Shirt"        $25.00
group-footer  "Category Total"  $40.00   ← per-group summary
group-header  "Golf"
  detail      "Driver"       $99.00
group-footer  "Category Total"  $99.00   ← per-group summary
summary       "Grand Total"  $139.00     ← report-level summary
```

Every aggregate column (`->sum()`, `->avg()`, `->min()`, `->max()`, `->count()`) is automatically included in both the group footer and the grand total. Footer and summary labels are blank by default; set them explicitly on non-aggregate columns via `->footerContent()` and `->summaryContent()`.

### ReportBuilder — multiple levels

Pass additional field keys (outermost first). Each level gets its own header/footer pair. The group footer at every level is a summary of that group's rows, with totals bubbled up automatically:

```php
ReportBuilder::create('sales-by-category-subcategory')
    ->columns([
        Column::make('description', 'Description', 0,   490)
            ->footerContent(new StaticExpression('Subtotal'))
            ->summaryContent(new StaticExpression('Grand Total')),
        Column::make('amount',      'Amount',       490, 82)
            ->sum()
            ->alignRight()
            ->format($currency),
    ])
    ->rows($rows)
    ->groupBy('category', 'subcategory')
    ->build();
```

Band sequence for two categories each with two subcategories:

```
col-header
group-header  "Apparel"
  group-header  "Headwear"
    detail        "Hat"       $15.00
  group-footer  "Subtotal"    $15.00   ← Headwear summary
  group-header  "Tops"
    detail        "Shirt"     $25.00
  group-footer  "Subtotal"    $25.00   ← Tops summary
group-footer  "Subtotal"      $40.00   ← Apparel summary (sum of subgroups)
group-header  "Golf"
  group-header  "Clubs"
    detail        "Driver"    $99.00
  group-footer  "Subtotal"    $99.00   ← Clubs summary
group-footer  "Subtotal"      $99.00   ← Golf summary
summary       "Grand Total"  $139.00   ← report-level summary
```

The same `groupFooterLabel` is used at all depths. Three or more levels work the same way — just add more keys to `->groupBy()`.

### DefinitionFiller — single level

Replace the `detail` band with a `group-header` / `detail` / `group-footer` slice. The filler collects all three into one group slice per distinct value of `group_by`:

```json
{
  "id": "grp_hdr", "type": "group-header", "group_by": "category",
  "elements": [
    { "id": "gv", "x": 0, "width": 572, "height": 16,
      "content": { "type": "group_value" } }
  ]
},
{
  "id": "detail", "type": "detail", "row_spacing": 2,
  "elements": [ ... ]
},
{
  "id": "grp_ftr", "type": "group-footer",
  "elements": [
    { "id": "lbl",   "x": 0,   "width": 480, "height": 14,
      "content": { "type": "text", "value": "Subtotal" } },
    { "id": "total", "x": 490, "width": 82,  "height": 14, "align": "right",
      "content": { "type": "aggregate", "field": "amount", "fn": "sum", "format": "currency" } }
  ]
}
```

Aggregate functions in `group-footer` operate over only the rows in that group. Aggregate functions in `summary` operate over all rows. `DefinitionFiller` supports one level of grouping; for nested groups use `ReportBuilder`.

---

## Advanced: Multiple Data Sources

A band can declare its own `data_source`, overriding the template-level default. This lets a single report pull from two independent queries.

In the JSON definition, add `data_source` to any band:

```json
{ "id": "detail", "type": "detail", "data_source": "secondary_source", "elements": [ ... ] }
```

In the filler, register both sources before calling `fill()`:

```php
$registry = new DataSourceRegistry();
$registry->register('primary_source',   $this->primaryData);
$registry->register('secondary_source', $this->secondaryData);

return (new DefinitionFiller($template, $registry, FormatterRegistry::defaults()))
    ->fill($params);
```

Each source is fetched at most once per `fill()` call regardless of how many bands reference it.

---

## Advanced: Band Callbacks

`DefinitionFiller::onBand()` lets you hook into band construction after the band is built but before it is added to the report. Return `null` to suppress the band; return the band (optionally modified) to include it. Callbacks chain.

```php
$filler = new DefinitionFiller($template, $registry, FormatterRegistry::defaults());

// Suppress zero-amount detail rows
$filler->onBand('detail', function (BandInstance $band, BandContext $ctx): ?BandInstance {
    return ((float) $ctx->getRow()['amount']) === 0.0 ? null : $band;
});

return $filler->fill($params);
```

`BandContext` exposes:

| Method | Available in |
|---|---|
| `getRow()` | `detail` bands |
| `getGroupValue()` | `group-header`, `group-footer` |
| `getAggregateRows()` | `group-footer`, `summary` |
| `getParams()` | all bands |

---

## General Reference

### Formatters

| Name | Input | Output |
|---|---|---|
| `currency` | `12.5` | `$12.50` |
| `cents` | `1250` | `$12.50` |
| `integer` | `1234.0` | `1234` |
| `date` | `2026-01-15` | `Jan 15, 2026` |

### Page layout

- **Page size**: US Letter — 612 × 792 pt (8.5 × 11 in at 72 dpi)
- **Default margins**: defined in `PageConfig`; usable width ≈ 572 pt
- **Coordinate origin**: top-left; `y` increases downward
- **Units**: points (pt) throughout

All column `x` positions and `width` values must fit within the usable page width.
