# edgecase123/report-writer

A deterministic reporting pipeline for PHP 7.4+. Framework-agnostic — works in Symfony 5.x/7, Laravel 10+, or plain PHP.

Turns row data + a layout into pixel-honest paginated reports that render identically on screen and paper.

## Pipeline

```
   ReportBuilder (fluent PHP)  ─┐
                                 ├─►  ReportInstance  ─►  LayoutService  ─►  ReportStream  ─►  HtmlRenderer / JsonRenderer
   DefinitionFiller (JSON)     ─┘
```

**Invariant:** Fill never touches layout math; Layout never touches business data. The seam is `ReportFillerInterface::fill(array $params): ReportInstance`.

Three ways to fill:

- **`ReportBuilder`** — fluent immutable PHP. Fastest path for hand-coded tabular reports; supports nested grouping.
- **`DefinitionFiller`** — JSON template interpreter. Foundation for data-driven report authoring; supports one level of grouping.
- **Custom `ReportFillerInterface`** — hand-build a `ReportInstance` when neither shipped path fits.

## Install

```bash
composer require edgecase123/report-writer
```

Requires PHP 7.4 or higher. No other runtime dependencies.

## Quick example — plain PHP

```php
use ReportWriter\Builder\Column;
use ReportWriter\Builder\ReportBuilder;
use ReportWriter\Layout\Flattener;
use ReportWriter\Layout\LayoutService;
use ReportWriter\Layout\PageConfig;
use ReportWriter\Registry\FormatterRegistry;
use ReportWriter\Renderer\HtmlRenderer;

$currency = FormatterRegistry::defaults()->get('currency');

$instance = ReportBuilder::create('daily-sales')
    ->title('Daily Sales')
    ->columns([
        Column::make('date',        'Date',        0,   120),
        Column::make('description', 'Description', 130, 280),
        Column::make('amount',      'Amount',      490, 82)
            ->sum()
            ->alignRight()
            ->format($currency),
    ])
    ->rows([
        ['date' => '2026-08-22', 'description' => 'Espresso', 'amount' => 18.00],
        ['date' => '2026-08-22', 'description' => 'Latte',    'amount' => 6.50],
    ])
    ->build();

$layout  = new LayoutService(new Flattener(), new PageConfig());
$stream  = $layout->layout($instance);
$html    = (new HtmlRenderer(new PageConfig()))->render($stream);

echo $html;
```

No framework required. The above is a complete working example.

## Wiring in Symfony 5.x / 7

Register a data source and a filler in `config/services.yaml`:

```yaml
services:
    App\Reporting\DataSource\DbalDailySalesProvider:
        autowire: true
    ReportWriter\Interfaces\ReportDataSourceInterface:
        alias: App\Reporting\DataSource\DbalDailySalesProvider

    App\Reporting\Fillers\DailySalesFiller:
        autowire: true
```

Controller:

```php
namespace App\Controller;

use App\Reporting\Fillers\DailySalesFiller;
use ReportWriter\Layout\Flattener;
use ReportWriter\Layout\LayoutService;
use ReportWriter\Layout\PageConfig;
use ReportWriter\Renderer\HtmlRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReportController
{
    /** @Route("/reports/daily-sales", methods={"GET"}) */
    public function dailySales(Request $request, DailySalesFiller $filler): Response
    {
        $instance = $filler->fill(['date' => $request->query->get('date')]);
        $layout   = new LayoutService(new Flattener(), new PageConfig());
        $stream   = $layout->layout($instance);
        $html     = (new HtmlRenderer(new PageConfig()))->render($stream);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
```

## Wiring in Laravel 10+

Register a service provider:

```php
namespace App\Providers;

use App\Reporting\DataSource\EloquentDailySalesProvider;
use ReportWriter\Interfaces\ReportDataSourceInterface;
use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportDataSourceInterface::class, EloquentDailySalesProvider::class);
    }
}
```

Controller:

```php
namespace App\Http\Controllers;

use App\Reporting\Fillers\DailySalesFiller;
use ReportWriter\Layout\Flattener;
use ReportWriter\Layout\LayoutService;
use ReportWriter\Layout\PageConfig;
use ReportWriter\Renderer\HtmlRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function dailySales(Request $request, DailySalesFiller $filler): Response
    {
        $instance = $filler->fill(['date' => $request->query('date')]);
        $layout   = new LayoutService(new Flattener(), new PageConfig());
        $stream   = $layout->layout($instance);

        return response((new HtmlRenderer(new PageConfig()))->render($stream))
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
}
```

## Column content expressions

Every cell in a report is a **content expression** evaluated when the band is built. Four expression types:

| Expression | Purpose | Example |
|---|---|---|
| `StaticExpression($text)` | Literal string, unchanged | `new StaticExpression('Grand Total')` |
| `FieldExpression($field)` | Value from current row | `new FieldExpression('item_name')` (default for columns) |
| `AggregateExpression($fn, $field)` | Computed over rows in scope | `sum`/`avg`/`min`/`max`/`count` — use `->sum()` etc. shortcuts |
| `ComputedExpression($fn)` | Arbitrary callable | `new ComputedExpression(fn($ctx) => count($ctx->aggregateRows) > 0 ? '✓' : '')` |

## `ReportBuilder` API reference

| Method | Description |
|---|---|
| `ReportBuilder::create($id)` | Start a new builder |
| `->title($text, $height = 30)` | Add a title band |
| `->columns(Column[])` | Define columns |
| `->rows($rows)` | Provide row data as an array of associative arrays |
| `->rowHeight($pt)` | Override the default row height (12 pt) |
| `->groupBy($field, ...)` | Group rows (outermost first) |
| `->build()` | Return the `ReportInstance` |

## `Column` API reference

| Method | Description |
|---|---|
| `Column::make($id, $header, $x, $width)` | Define a column |
| `->sum()` / `->avg()` / `->min()` / `->max()` / `->count()` | Aggregate over rows in scope |
| `->format(callable)` | Value formatter applied to detail + footer + summary |
| `->footerContent(ContentExpression)` | Override group-footer cell content |
| `->summaryContent(ContentExpression)` | Override summary cell content |
| `->alignRight()` / `->alignLeft()` / `->alignCenter()` | Text alignment |
| `->margin($left, $right = 0)` | Horizontal inset within the column cell |

## `DefinitionFiller` — JSON-driven reports

Interprets a JSON `ReportTemplate` generically. Great when the report definition needs to live in a database, be edited by non-developers, or ship without a code deploy.

Full JSON schema reference in `docs/architecture/fill-to-layout-schema.md` (in the parent repo).

## Reference implementation

The parent repo (this file lives at `writer/README.md` inside the `report-writer` project) ships a Slim 4 + SQLite + Docker Compose demo at `writer-app/` with 6 sample reports plus a split-screen JSON builder UI. That demo is **reference material only** — per project ADR-013, the library is framework-agnostic and no consumer needs Slim, Docker, or SQLite specifically. Adapt the demo's patterns to your own stack.

## Running the tests

```bash
composer install
vendor/bin/phpunit
```

Requires PHP 7.4+ and Composer 2.

## License

Proprietary — see the parent repository for licensing.
