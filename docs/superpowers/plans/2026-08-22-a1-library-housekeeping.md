# A1 — Library Housekeeping Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename the library from `foreup/reporting` → `edgecase123/report-writer`, delete unreferenced dead code, consolidate duplicated aggregate math, and fix the misleading fluent-return on `DefinitionFiller::onBand`. Library still passes its existing PHPUnit suite after every task.

**Architecture:** Sequential mechanical refactors first (aggregate consolidation, onBand fix, dead-code removal) while everything still lives under the old namespace — keeps diffs reviewable. Then the namespace/package rename in one atomic pass. Verify test-suite green at every commit.

**Tech Stack:** PHP 7.4+, PHPUnit 9.5, Composer 2. Work happens entirely inside the `writer/` directory. No frontend, no Docker, no Slim.

**Tickets addressed:** [001](../../tickets/001-aggregate-math-dry-consolidation.md), [002](../../tickets/002-delete-dead-code-definition-namespace.md), [006](../../tickets/006-definitionfiller-onband-immutability.md), [010](../../tickets/010-library-rename-to-edgecase.md).

**Related decisions:** [ADR-009](../../09-conventions/decisions/009-library-rename-to-edgecase.md), [ADR-013](../../09-conventions/decisions/013-framework-agnostic-library.md).

**Prerequisites:** PHP 7.4+ and Composer 2 available on the host (or a container with both). No other dependencies. The command `php --version` must show 7.4 or higher.

**Working directory for all commands below:** `/Users/leejenkins/dev/report-writer/writer/` unless stated otherwise.

---

## Task 0: Verify baseline

**Files:** No source changes. Confirms the test suite is green before any refactoring begins so future failures point at the change that caused them.

- [ ] **Step 0.1: Install composer dependencies**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer && composer install
```

Expected: composer downloads phpunit and its transitive deps into `vendor/`, prints `Generating autoload files` and `X packages installed`. No error output.

- [ ] **Step 0.2: Run the existing test suite**

Run:

```bash
vendor/bin/phpunit
```

Expected: green output ending with `OK (N tests, M assertions)` where N is roughly 100-120. Any red output = stop and investigate before proceeding; the baseline is unstable.

- [ ] **Step 0.3: Commit the vendor lockfile if it changed**

Run:

```bash
git status composer.lock 2>&1 || echo "not a git repo, skip"
```

If `writer/` is a git repo AND `composer.lock` shows as modified, commit it:

```bash
git add composer.lock && git commit -m "chore: refresh composer.lock baseline"
```

If not a git repo, skip. (The repo root shows `Is a git repository: false` per session context, but the `writer/` subdirectory may have its own git init — check.)

---

## Task 1: Extract `AggregateFunction` and delegate from both call sites (Ticket 001)

**Files:**
- Create: `writer/src/Expression/AggregateFunction.php`
- Modify: `writer/src/Expression/AggregateExpression.php` (delete inline `compute()` method, delegate to `AggregateFunction::apply`)
- Modify: `writer/src/Fill/DefinitionFiller.php` (delete inline `computeAggregate()` method, delegate to `AggregateFunction::apply`)
- Test (new): `writer/tests/Unit/Expression/AggregateFunctionTest.php`

**Why:** `DefinitionFiller::computeAggregate()` at line 285 and `AggregateExpression::compute()` at line 40 implement the same sum/avg/min/max/count math twice, keyed by the same `$fn` string. Both also carry a `switch` on the aggregate function name (OCP smell). Extracting to a single static utility deletes both duplications with one file.

- [ ] **Step 1.1: Write the failing test**

Create `writer/tests/Unit/Expression/AggregateFunctionTest.php`:

```php
<?php

declare(strict_types=1);

namespace foreup\Reporting\Tests\Unit\Expression;

use foreup\Reporting\Expression\AggregateFunction;
use PHPUnit\Framework\TestCase;

final class AggregateFunctionTest extends TestCase
{
    /** @dataProvider aggregateCases */
    public function testApply(string $fn, array $rows, string $field, float $expected): void
    {
        $this->assertSame($expected, AggregateFunction::apply($fn, $rows, $field));
    }

    public static function aggregateCases(): array
    {
        $rows = [
            ['amount' => 10.0],
            ['amount' => 20.0],
            ['amount' => 30.0],
        ];

        return [
            'sum'         => ['sum',   $rows, 'amount', 60.0],
            'default sum' => ['xxx',   $rows, 'amount', 60.0],
            'avg'         => ['avg',   $rows, 'amount', 20.0],
            'min'         => ['min',   $rows, 'amount', 10.0],
            'max'         => ['max',   $rows, 'amount', 30.0],
            'count'       => ['count', $rows, 'amount', 3.0],
            'empty rows'  => ['sum',   [],    'amount', 0.0],
            'missing field coerced to 0' => ['sum', [['other' => 5]], 'amount', 0.0],
        ];
    }
}
```

- [ ] **Step 1.2: Run the test to verify it fails**

Run:

```bash
vendor/bin/phpunit tests/Unit/Expression/AggregateFunctionTest.php
```

Expected: FAIL with `Error: Class "foreup\Reporting\Expression\AggregateFunction" not found`.

- [ ] **Step 1.3: Create `AggregateFunction`**

Create `writer/src/Expression/AggregateFunction.php`:

```php
<?php

declare(strict_types=1);

namespace foreup\Reporting\Expression;

/**
 * Shared aggregate math for sum/avg/min/max/count.
 *
 * Both DefinitionFiller and AggregateExpression delegate here so the switch
 * on function name lives in exactly one place.
 */
final class AggregateFunction
{
    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public static function apply(string $fn, array $rows, string $field): float
    {
        if (empty($rows)) {
            return 0.0;
        }

        $values = array_map(static fn ($row) => (float) ($row[$field] ?? 0), $rows);

        switch ($fn) {
            case 'count': return (float) count($values);
            case 'avg':   return array_sum($values) / count($values);
            case 'min':   return (float) min($values);
            case 'max':   return (float) max($values);
            default:      return (float) array_sum($values); // sum
        }
    }
}
```

- [ ] **Step 1.4: Run the test to verify it passes**

Run:

```bash
vendor/bin/phpunit tests/Unit/Expression/AggregateFunctionTest.php
```

Expected: PASS — `OK (8 tests, 8 assertions)`.

- [ ] **Step 1.5: Delegate `AggregateExpression::compute()` to `AggregateFunction::apply()`**

Edit `writer/src/Expression/AggregateExpression.php`. Replace the entire private `compute()` method:

```php
    private function compute(array $rows): float
    {
        if (empty($rows)) {
            return 0.0;
        }
        $values = array_map(fn($r) => (float) ($r[$this->field] ?? 0), $rows);
        switch ($this->fn) {
            case 'avg':   return array_sum($values) / count($values);
            case 'min':   return (float) min($values);
            case 'max':   return (float) max($values);
            case 'count': return (float) count($values);
            default:      return array_sum($values); // sum
        }
    }
```

with:

```php
    private function compute(array $rows): float
    {
        return AggregateFunction::apply($this->fn, $rows, $this->field);
    }
```

- [ ] **Step 1.6: Delegate `DefinitionFiller::computeAggregate()` to `AggregateFunction::apply()`**

Edit `writer/src/Fill/DefinitionFiller.php`. First, add the import to the `use` block at the top:

```php
use foreup\Reporting\Expression\AggregateFunction;
```

Then replace the entire `computeAggregate()` method:

```php
    private function computeAggregate(array $rows, string $field, string $fn): float
    {
        $values = array_map(fn ($row) => (float) ($row[$field] ?? 0), $rows);

        if (empty($values)) {
            return 0.0;
        }

        switch ($fn) {
            case 'count': return (float) count($values);
            case 'avg':   return array_sum($values) / count($values);
            case 'min':   return (float) min($values);
            case 'max':   return (float) max($values);
            default:      return (float) array_sum($values); // sum
        }
    }
```

with:

```php
    private function computeAggregate(array $rows, string $field, string $fn): float
    {
        return AggregateFunction::apply($fn, $rows, $field);
    }
```

- [ ] **Step 1.7: Run the full test suite**

Run:

```bash
vendor/bin/phpunit
```

Expected: green. All prior tests still pass, plus the 8 new `AggregateFunctionTest` cases. Total assertions increased by 8.

- [ ] **Step 1.8: Commit**

If the writer/ directory is a git repo, commit:

```bash
git add src/Expression/AggregateFunction.php \
        src/Expression/AggregateExpression.php \
        src/Fill/DefinitionFiller.php \
        tests/Unit/Expression/AggregateFunctionTest.php && \
git commit -m "refactor(expression): extract AggregateFunction; delete duplicate switch

- New static helper AggregateFunction::apply() unifies sum/avg/min/max/count math
- AggregateExpression::compute() and DefinitionFiller::computeAggregate() now delegate
- Closes Ticket 001"
```

If not a git repo, save the file list to a session note for later commit.

---

## Task 2: Fix `DefinitionFiller::onBand` fluent-mutation (Ticket 006)

**Files:**
- Modify: `writer/src/Fill/DefinitionFiller.php` — change return type from `self` to `void`

**Why:** `onBand` returns `self` (implying immutable-fluent) but mutates `$this->bandCallbacks` in place. Grep across the repo confirmed no chaining callers exist, so switching to `void` is safe and honest.

- [ ] **Step 2.1: Confirm no chaining callers exist**

Run:

```bash
grep -rn "onBand.*->.*onBand\|onBand(.*)->" /Users/leejenkins/dev/report-writer/writer/ 2>&1 | grep -v vendor
```

Expected: no matches. If any appear, stop and revisit the plan — those callers would break under the void change.

- [ ] **Step 2.2: Change the signature and drop the return**

Edit `writer/src/Fill/DefinitionFiller.php`, replace:

```php
    /**
     * Register a callback for a band. The callback fires after the band is built,
     * before it is added to the report. Return null to suppress the band, or return
     * a (modified) BandInstance to use instead.
     *
     * Callbacks chain: each receives the output of the previous. If any returns null
     * the band is suppressed and remaining callbacks are skipped.
     *
     * @param callable(BandInstance, BandContext): ?BandInstance $callback
     */
    public function onBand(string $bandId, callable $callback): self
    {
        $this->bandCallbacks[$bandId][] = $callback;
        return $this;
    }
```

with:

```php
    /**
     * Register a callback for a band. The callback fires after the band is built,
     * before it is added to the report. Return null to suppress the band, or return
     * a (modified) BandInstance to use instead.
     *
     * Callbacks chain: each receives the output of the previous. If any returns null
     * the band is suppressed and remaining callbacks are skipped.
     *
     * This method mutates $this — it is NOT fluent. Call it and discard the return.
     *
     * @param callable(BandInstance, BandContext): ?BandInstance $callback
     */
    public function onBand(string $bandId, callable $callback): void
    {
        $this->bandCallbacks[$bandId][] = $callback;
    }
```

- [ ] **Step 2.3: Run the full test suite**

Run:

```bash
vendor/bin/phpunit
```

Expected: green. All 8 `onBand` call sites in `DefinitionFillerTest.php` discard the return already — no test changes needed.

- [ ] **Step 2.4: Commit**

```bash
git add src/Fill/DefinitionFiller.php && \
git commit -m "fix(fill): drop misleading fluent return on DefinitionFiller::onBand

Signature was self but the method mutates \$this. Grep confirmed no chaining
callers exist. Change to void so the mutation is honest.

Closes Ticket 006"
```

---

## Task 3: Delete `Interfaces/DataProviderInterface` and `Definition/*` dead code (Ticket 002)

**Files:**
- Delete: `writer/src/Interfaces/DataProviderInterface.php`
- Delete: `writer/src/Definition/BandDefinition.php`
- Delete: `writer/src/Definition/ElementDefinition.php`
- Delete: `writer/src/Definition/ReportDefinition.php`
- Delete: `writer/src/Definition/` (empty directory after files removed)

**Why:** `Interfaces/DataProviderInterface.php` is defined and never used. The whole `Definition/*` namespace is orphaned — runtime code uses `Template/*Template.php` classes for the same shapes. Two parallel type hierarchies for the same domain concept, waiting to grow a third caller.

- [ ] **Step 3.1: Grep-verify no live references**

Run:

```bash
grep -rn "DataProviderInterface" /Users/leejenkins/dev/report-writer/writer/ 2>&1 | grep -v vendor | grep -v "DataProviderInterface.php:"
```

Expected: no matches (the only line that mentions it should be the file's own definition, which we filter out).

```bash
grep -rn "foreup\\\\Reporting\\\\Definition\\\\" /Users/leejenkins/dev/report-writer/writer/ 2>&1 | grep -v vendor | grep -v "src/Definition/"
```

Expected: no matches.

- [ ] **Step 3.2: Delete the files**

Run:

```bash
rm /Users/leejenkins/dev/report-writer/writer/src/Interfaces/DataProviderInterface.php
rm -r /Users/leejenkins/dev/report-writer/writer/src/Definition/
```

- [ ] **Step 3.3: Regenerate autoload and run the test suite**

Run:

```bash
composer dump-autoload
vendor/bin/phpunit
```

Expected: `composer dump-autoload` prints `Generating autoload files` and finishes clean. PHPUnit output green.

- [ ] **Step 3.4: Commit**

```bash
git add -A src/Interfaces/ src/Definition/ composer.json composer.lock 2>/dev/null || true
git commit -m "chore: delete unreferenced DataProviderInterface and Definition/ namespace

Both were dead code. Runtime uses ReportDataSourceInterface and Template/* classes
for these shapes. Grep confirmed zero external references before deletion.

Closes Ticket 002"
```

---

## Task 4: Rename composer.json package + PSR-4 mappings

**Files:**
- Modify: `writer/composer.json`

**Why:** First step of the library rename. Establishes the new package name and namespace root before we start rewriting `namespace` / `use` statements in source files.

- [ ] **Step 4.1: Edit composer.json**

Edit `writer/composer.json`. Replace:

```json
{
    "name": "foreup/reporting",
    "version": "0.1.0",
    "description": "Deterministic reporting pipeline: Definition → Fill → Layout → Stream",
    "type": "library",
    "authors": [
        {
            "name": "foreUP",
            "email": "dev@foreup.com",
            "role": "Developer"
        }
    ],
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.5"
    },
    "autoload": {
        "psr-4": {
            "foreup\\Reporting\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "foreup\\Reporting\\Tests\\": "tests/"
        }
    }
}
```

with:

```json
{
    "name": "edgecase123/report-writer",
    "version": "0.1.0",
    "description": "Deterministic reporting pipeline: Fill → Layout → Stream → Render. Framework-agnostic.",
    "type": "library",
    "authors": [
        {
            "name": "edgecase",
            "email": "edgecase123@gmail.com",
            "role": "Developer"
        }
    ],
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.5"
    },
    "autoload": {
        "psr-4": {
            "ReportWriter\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "ReportWriter\\Tests\\": "tests/"
        }
    }
}
```

- [ ] **Step 4.2: Regenerate autoload**

Run:

```bash
composer dump-autoload
```

Expected: succeeds. Autoloader now points `ReportWriter\` at `src/` but the source files still declare the old namespace — running tests at this point WILL fail. That's expected; we fix in the next task.

- [ ] **Step 4.3: Do not commit yet**

Composer.json is in an inconsistent state with the source files until Task 5 completes. Batch them into one commit for atomicity.

---

## Task 5: Rewrite namespace declarations and use statements throughout `src/` and `tests/`

**Files:** Every `.php` file under `writer/src/` and `writer/tests/`.

**Why:** Second step of the rename. The composer.json now expects `ReportWriter\`; the source files must match. Two mechanical find-replace operations across all PHP files:

1. `namespace foreup\Reporting` → `namespace ReportWriter`
2. `use foreup\Reporting` → `use ReportWriter`

- [ ] **Step 5.1: Preview what will change (dry-run)**

Run:

```bash
grep -rn "namespace foreup\\\\Reporting\|use foreup\\\\Reporting" \
    /Users/leejenkins/dev/report-writer/writer/src \
    /Users/leejenkins/dev/report-writer/writer/tests \
    | grep -v vendor | wc -l
```

Expected: a number between 60 and 120 (roughly N source files × 1 namespace declaration + N-ish use statements). Note the number — you'll compare after.

- [ ] **Step 5.2: Rewrite `namespace` declarations**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer && \
grep -rl "namespace foreup\\\\Reporting" src tests | \
    xargs sed -i.bak 's/namespace foreup\\Reporting/namespace ReportWriter/g'
```

- [ ] **Step 5.3: Rewrite `use` statements**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer && \
grep -rl "use foreup\\\\Reporting" src tests | \
    xargs sed -i.bak 's/use foreup\\Reporting/use ReportWriter/g'
```

- [ ] **Step 5.4: Clean up sed backup files**

Run:

```bash
find /Users/leejenkins/dev/report-writer/writer/src \
     /Users/leejenkins/dev/report-writer/writer/tests \
     -name "*.bak" -delete
```

- [ ] **Step 5.5: Verify zero foreUP references remain in code**

Run:

```bash
grep -rn "foreup\\\\Reporting\|foreup/reporting" \
    /Users/leejenkins/dev/report-writer/writer/src \
    /Users/leejenkins/dev/report-writer/writer/tests
```

Expected: no output. If ANY line is returned, hand-edit each occurrence — probably a doc comment or an unusual escape context sed missed.

- [ ] **Step 5.6: Regenerate autoload and run the full suite**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer && \
composer dump-autoload && \
vendor/bin/phpunit
```

Expected: PHPUnit green. If red, the sed replacements probably missed a case — grep for the class name in question, fix by hand, re-run.

- [ ] **Step 5.7: Commit rename atomically**

```bash
git add composer.json composer.lock src/ tests/ && \
git commit -m "refactor: rename to edgecase123/report-writer + ReportWriter\\ namespace

Mechanical rename across composer.json + all PHP files under src/ and tests/.
Package renamed: foreup/reporting -> edgecase123/report-writer
Namespace root renamed: foreup\\Reporting\\ -> ReportWriter\\

Per ADR-013, the library must be framework-agnostic and ownership-neutral so
Symfony, Laravel, and plain-PHP consumers can all adopt it. The old name
implied foreUP ownership and a specific consumer.

Closes Ticket 010"
```

---

## Task 6: Update `writer/README.md`

**Files:**
- Modify: `writer/README.md`

**Why:** The README references the old package name, the old namespace, foreUP-specific paths (`api_rest/`, `foreup\rest\models\services\Reporting\`), and Symfony-only wiring examples. Post-rename per [ADR-013](../../09-conventions/decisions/013-framework-agnostic-library.md), the README should describe a framework-agnostic library with wiring examples for multiple frameworks.

- [ ] **Step 6.1: Read the current README**

Read `/Users/leejenkins/dev/report-writer/writer/README.md` in full. Note the sections that reference:

- `foreup/reporting` (package name)
- `foreup\Reporting\` (namespace)
- `api_rest/packages/foreup-reporting/` (outer app path)
- `foreup\rest\models\services\Reporting\` (outer app namespace)
- Symfony `AbstractReportingController`, `permissions.yaml`, `services.php`
- Docker container `php-apache` (outer app's stack)

- [ ] **Step 6.2: Rewrite the README**

Overwrite `writer/README.md` with:

```markdown
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
```

- [ ] **Step 6.3: Verify no foreUP references remain in the README**

Run:

```bash
grep -in "foreup\|api_rest" /Users/leejenkins/dev/report-writer/writer/README.md
```

Expected: no output.

- [ ] **Step 6.4: Commit**

```bash
git add README.md && \
git commit -m "docs: rewrite writer/README.md for framework-agnostic library

Removes foreUP-specific references. Adds wiring examples for Symfony 5.x/7,
Laravel 10+, and plain PHP per ADR-013. New composer name and namespace
reflected throughout."
```

---

## Task 7: Sanity-check final state

**Files:** No changes. Verification only.

- [ ] **Step 7.1: Zero foreUP references in library**

Run:

```bash
grep -rn "foreup" \
    /Users/leejenkins/dev/report-writer/writer/composer.json \
    /Users/leejenkins/dev/report-writer/writer/src \
    /Users/leejenkins/dev/report-writer/writer/tests \
    /Users/leejenkins/dev/report-writer/writer/README.md \
    2>&1 | grep -v vendor | grep -iv "^Binary"
```

Expected: no output.

- [ ] **Step 7.2: No accidental Slim / Symfony / Laravel imports in src/**

Per [ADR-013](../../09-conventions/decisions/013-framework-agnostic-library.md), the library must never import framework namespaces.

Run:

```bash
grep -rn "^use Slim\|^use Symfony\|^use Illuminate\|^use Laravel" \
    /Users/leejenkins/dev/report-writer/writer/src
```

Expected: no output.

- [ ] **Step 7.3: Dead-code directories are truly gone**

Run:

```bash
test -d /Users/leejenkins/dev/report-writer/writer/src/Definition && echo "PROBLEM: Definition/ still exists" || echo "OK: Definition/ removed"
test -f /Users/leejenkins/dev/report-writer/writer/src/Interfaces/DataProviderInterface.php && echo "PROBLEM: DataProviderInterface still exists" || echo "OK: DataProviderInterface removed"
```

Expected: both print `OK: ...`.

- [ ] **Step 7.4: Full test suite still green**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer && \
composer dump-autoload && \
vendor/bin/phpunit
```

Expected: green. Assertion count should be **prior baseline + 8** (the 8 new `AggregateFunctionTest` cases from Task 1).

- [ ] **Step 7.5: The `AggregateFunction` extraction actually deleted the duplication**

Run:

```bash
grep -n "case 'avg'\|case 'min'\|case 'max'\|case 'count'" \
    /Users/leejenkins/dev/report-writer/writer/src/Expression/AggregateExpression.php \
    /Users/leejenkins/dev/report-writer/writer/src/Fill/DefinitionFiller.php \
    /Users/leejenkins/dev/report-writer/writer/src/Expression/AggregateFunction.php
```

Expected: matches ONLY in `AggregateFunction.php`. Zero matches in the other two files. If either `AggregateExpression.php` or `DefinitionFiller.php` still contains the switch, Task 1 was incomplete.

- [ ] **Step 7.6: `onBand` signature is `void`**

Run:

```bash
grep -A1 "public function onBand" /Users/leejenkins/dev/report-writer/writer/src/Fill/DefinitionFiller.php
```

Expected: the signature line ends with `: void`, and the body no longer contains `return $this`.

- [ ] **Step 7.7: Composer package name and namespace are correct**

Run:

```bash
grep '"name"' /Users/leejenkins/dev/report-writer/writer/composer.json
grep -E "ReportWriter" /Users/leejenkins/dev/report-writer/writer/composer.json
```

Expected: `"name": "edgecase123/report-writer"` and PSR-4 mappings pointing at `ReportWriter\\` and `ReportWriter\\Tests\\`.

- [ ] **Step 7.8: If everything above is green, commit the sanity-check state**

No file changes to commit. But mark the plan complete:

```bash
echo "A1 plan complete: library renamed to edgecase123/report-writer, dead code removed, aggregate math consolidated, onBand fixed. Test suite green." >> /tmp/report-writer-progress.log
```

---

## Post-plan followups

Update the source tickets to closed:

- `docs/tickets/001-aggregate-math-dry-consolidation.md` — set status to Closed
- `docs/tickets/002-delete-dead-code-definition-namespace.md` — set status to Closed
- `docs/tickets/006-definitionfiller-onband-immutability.md` — set status to Closed
- `docs/tickets/010-library-rename-to-edgecase.md` — set status to Closed
- `docs/tickets/README.md` — update the ledger's Status column for each

Any references to `foreup\Reporting` in the outer `docs/` tree also need updating (`docs/architecture/*`, `docs/09-conventions/*`, `docs/01-overview/*`, `docs/03-concepts/*`). Grep from repo root:

```bash
grep -rn "foreup" /Users/leejenkins/dev/report-writer/docs/
```

Update each hit as a small follow-on commit — not part of this plan's task decomposition since it's docs work outside the `writer/` boundary.

---

## What this plan does NOT do

Deferred to A2 or later plans, or to lower-priority tickets:

- Any code under `writer-app/` (that's A2+)
- Any Docker or docker-compose changes
- Any frontend changes
- Refactoring `ReportBuilder`'s 4-way element loop (Ticket 003)
- Collapsing `DefinitionFiller`'s 3-way band builder (Ticket 004)
- Extracting shared formatter block from Expression classes (Ticket 005) — low priority, opportunistic
- Fixing the zoom transform-origin frontend bug (Ticket 007)
- Adding `vue-tsc` step (Ticket 008)
- `@page { margin: 0 }` verification (Ticket 009)
- Zoom preset UX (Ticket 011)

Everything else in Tickets 012–015 is future work.
