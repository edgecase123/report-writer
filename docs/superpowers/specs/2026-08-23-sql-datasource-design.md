# SQL Data Source Foundation

**Status:** Draft — pending Lee's review
**Date:** 2026-08-23
**Ticket:** none (foundation-level architectural spec; follow-up implementation tickets to be filed post-approval)
**Scope:** `writer/src/Interfaces/`, `writer/src/DataSource/`, `writer/src/Builder/ReportBuilder.php`, `writer/src/Fill/DefinitionFiller.php`, `writer-app/src/Kernel.php`, `writer-app/src/Reports/`, associated tests

## Problem

The library currently expresses "a report's data source" through `ReportWriter\Interfaces\ReportDataSourceInterface` — a one-method contract (`fetchRows(array $params): array`). Concrete implementations (`SqliteDailySalesProvider`, `SqliteSalesByCategoryProvider`, `SqliteOpenTabsProvider`, `SqliteSalesByCategoryItemProvider`) are one hand-written PHP class per report, each hard-coding: PDO reference, SQL string, per-report `$params['date']` validation (until PR #28 extracted `DateParam`), and post-fetch row-type coercion (`(int)`, `(string)` casts).

At four reports we already saw the DRY violation surface (PR #28). At six+ (A3 close) the tax becomes structural. Beyond the class-per-report cost, the current interface exposes nothing about the query's *shape*: consumers must trust the returned rows to have the columns they need, with no compile-time or runtime declaration of what `fetchRows()` returns.

**Three unaddressed audiences make the current shape strategically incomplete:**

1. **Devs writing reports today.** Lee's team writes reports as Symfony controller methods with freehand Doctrine DBAL/ORM queries married to Twig templates — one bespoke query per report. This library aims to put that pattern on rails. The immediate consumer wants a fluent, composable, ergonomically dense way to declare "here is a data source, here is a report template that reads from it."
2. **An AI report-authorship system, months out.** The AI will have schema + DB access and needs a machine-readable API to compose reports programmatically from natural-language user requests. It benefits from every atom declaring its `paramSpecs()` and `columnSpec()` so it can compose without executing.
3. **An eventual visual UI report builder.** Not immediate priority, but the API surface should not paint itself into a corner.

## Consumer priorities (ordered)

1. **Dev-authored reports.** Fluent `ReportBuilder` API is the deliverable. Framework-on-rails for the "controller-method + freehand SQL + Twig" pattern the team runs today.
2. **Reusable component library.** Registered data-source atoms + composition primitives that the fluent API consumes. Dev writing "sales by staff" does not re-author staff-lookup or the join.
3. **AI report-authorship (months out).** Deployment locus is flexible — the AI system is a separate app but can run inside the main application (embedded or standalone). API-shape decisions keep AI usability as a check, but AI-specific features (introspection API, safety infra) ship when the AI bridge lands, not now.
4. **UI builder (future).** Not gating. Designed around what the AI/dev API already supports.

## Design principles

- **The `ReportBuilder` fluent API is the source of truth for what a report is.** Every other consumer (JSON template, AI, UI builder) is a projection of the same call graph.
- **Data-source composition happens in code, not in JSON templates.** SQL is dev-authored; the JSON template's `data_source` field references named registered atoms (never contains inline SQL).
- **Atoms are parameterised over a *family* of reports, not a single one** — see § *Atom parameterisation discipline* below.
- **Machine-introspectable-by-design.** Every atom declares its params and column shape. AI enablement is a free consequence of good design, not a feature bolt-on.
- **Streaming is a first-class shape.** Row sets flow as `iterable`. Executor and value-object atoms yield rows without materialising. Consumers that need to iterate twice materialise explicitly (single line of code).
- **Framework agnostic (per [ADR-013](../../09-conventions/decisions/013-framework-agnostic-library.md)).** The library's SQL execution layer is a pluggable seam. PDO ships in `writer/`; Doctrine DBAL is a near-term adapter; Eloquent is a companion package if a Laravel consumer arrives.

## Design

### Interface: `SqlExecutor`

The pluggable seam between the library and a specific DB access layer.

```php
namespace ReportWriter\Interfaces;

interface SqlExecutor
{
    /**
     * @param array<string, scalar|null> $params  Bound parameters keyed by placeholder name (":date" etc.)
     * @return iterable<int, array<string, scalar|null>>  Streaming rows; caller MAY materialise.
     */
    public function fetchAll(string $sql, array $params): iterable;
}
```

**Implementations** (this spec ships (a); (b) is near-term follow-up; (c)+ are companion packages):

- (a) `writer/src/DataSource/PdoExecutor.php` — takes `PDO`; `while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { yield $row; }`.
- (b) `writer/src/DataSource/DoctrineDbalExecutor.php` — takes `Doctrine\DBAL\Connection`; wraps `Connection::executeQuery()->iterateAssociative()`.
- (c) `EloquentExecutor` (companion package `report-writer-laravel`) — wraps `DB::select(...)`.
- (d) Test / in-memory / audit-recording / etc. — trivial to author; the interface is one method.

**Type coercion** does not live in the executor. Executors return raw scalar rows; coercion happens in `NamedSqlDataSource` (see below) driven by `columnSpec`.

### Interface: `SqlDataSource`

A narrower specialization of `ReportDataSourceInterface` that adds SQL-level introspection — the property the AI (and eventual UI builder) needs.

```php
namespace ReportWriter\Interfaces;

use ReportWriter\Interfaces\ReportDataSourceInterface;

interface SqlDataSource extends ReportDataSourceInterface
{
    public function name(): string;                    // stable id, e.g. "orders-closed-on-date"
    public function sql(): string;                     // parametrised SQL (:placeholders only)
    public function paramSpecs(): array;               // array<string, ParamSpec>
    public function columnSpec(): array;               // array<string, ColumnSpec> — declared output shape
    // fetchRows(array): iterable — inherited from ReportDataSourceInterface
}
```

`ReportDataSourceInterface` remains the wide seam. Non-SQL sources (computed aggregates, HTTP fetches, cached readers, composites — see `EnrichedDataSource`) implement only the wide interface.

**Naming rationale:** `SqlDataSource` (not `SqlStatement`) so the parent-child relationship with `ReportDataSourceInterface` is self-evident in the class hierarchy.

### Value-object: `NamedSqlDataSource`

The general-purpose implementation of `SqlDataSource` — the "one class covers most SQL reports" ergonomics.

```php
namespace ReportWriter\DataSource;

use ReportWriter\Interfaces\SqlDataSource;
use ReportWriter\Interfaces\SqlExecutor;

final class NamedSqlDataSource implements SqlDataSource
{
    public function __construct(
        SqlExecutor $executor,
        string      $name,
        string      $sql,
        array       $paramSpecs,   // array<string, ParamSpec>
        array       $columnSpec    // array<string, ColumnSpec>
    );

    public function fetchRows(array $params): iterable
    {
        foreach ($this->executor->fetchAll($this->sql, $this->bind($params)) as $row) {
            yield $this->coerce($row);  // applies columnSpec-driven type coercion
        }
    }
    // + name(), sql(), paramSpecs(), columnSpec() accessors
}
```

**Also permitted:** direct subclass implementations of `SqlDataSource` when a report warrants its own class (per-report methods, complex parameter validation, cross-query composition). The value-object is a convenience, not a mandate.

### Supporting types: `ParamSpec` and `ColumnSpec`

Two small value-objects new to `writer/` in the spike PR, shared by `SqlDataSource` implementations:

```php
namespace ReportWriter\Interfaces;   // or writer/src/Spec/ — implementer's choice

final class ParamSpec
{
    public static function date(string $name, bool $required = true): self;
    public static function int(string $name, ?int $min = null, ?int $max = null, bool $required = true): self;
    public static function string(string $name, bool $required = true): self;
    public static function enum(string $name, array $allowed, bool $required = true): self;
    // + name/type/required/validate($value) accessors
}

final class ColumnSpec
{
    public static function int(?string $label = null): self;
    public static function string(?string $label = null): self;
    public static function cents(?string $label = null): self;    // int cents, coerces + drives currency formatting
    public static function float(?string $label = null): self;
    public static function date(?string $label = null): self;      // ISO YYYY-MM-DD string
    public static function datetime(?string $label = null): self;  // ISO 8601 string
    // + type/label/coerce($value) accessors
}
```

Type vocabulary matches the atom-parameterisation discipline (§ above). No `bool`, no arbitrary column names — deferred until a report requires them.

`writer-app`'s existing `ParamSpec` (used in `ReportRegistry`) either merges with this or the app-side one gets renamed — implementation-plan decision, no user-visible impact.

### Composite: `EnrichedDataSource`

The first composition primitive — asymmetric enrichment of a primary row set with columns pulled from a lookup source. Implements the *wide* `ReportDataSourceInterface` (a composite has no executable SQL of its own).

```php
namespace ReportWriter\DataSource;

use ReportWriter\Interfaces\ReportDataSourceInterface;
use ReportWriter\Interfaces\SqlDataSource;

final class EnrichedDataSource implements ReportDataSourceInterface
{
    /**
     * @param array<string, string> $on      ['primary_col' => 'lookup_col'] — join key(s)
     * @param array<string, string> $select  ['lookup_col' => 'target_alias'] — columns to pull in
     * @param 'left'|'inner'        $joinType  Default 'left' (missing lookup → nulls on lookup cols)
     */
    public function __construct(
        ReportDataSourceInterface $primary,
        SqlDataSource             $lookup,   // must be SqlDataSource — needs columnSpec for validation
        array                     $on,
        array                     $select,
        string                    $joinType = 'left'
    );

    public function fetchRows(array $params): iterable
    {
        $lookupHash = $this->materializeLookup($params);   // full materialisation of lookup side
        foreach ($this->primary->fetchRows($params) as $primaryRow) {
            yield $this->merge($primaryRow, $lookupHash);
        }
    }
}
```

**Ctor-time validation** (fail-fast before the UI/AI user hits "run"):
- Every key in `$on` (both sides) must exist in the respective source's `columnSpec()`.
- Every key in `$select` must exist in `$lookup->columnSpec()`.
- No `select` target alias may collide with a `$primary->columnSpec()` column name.

**Composability is transitive:** because `EnrichedDataSource implements ReportDataSourceInterface`, expressions like `enrich(enrich(orders, staff), category)` compose naturally.

**Not-yet-shipped composition primitives** (tracked as follow-up tickets, unblock when a real report needs them):
- `PeriodOverPeriodDataSource(atom, windowA, windowB, on)` — same atom, two param sets, merged on key. Unlocks period-over-period, YoY, cumulative-to-date, cohort retention.
- `LeftAntiJoinDataSource(primary, exclusion, on)` — negative existence. Unlocks "never-ordered items," "churned customers," "unmapped inventory."

### Fluent API: `ReportBuilder::dataSource()`

New fluent method on the code-first builder.

```php
namespace ReportWriter\Builder;

class ReportBuilder
{
    public function dataSource(ReportDataSourceInterface $source): self;
    // Replaces the current ad-hoc `->rows([...])` pattern for SQL-backed reports.
    // Accepts anything that produces rows — SqlDataSource, EnrichedDataSource, or a bespoke class.
}
```

Existing `->rows(array)` stays for hard-coded row sets (tests, tiny fixtures).

## Iterable + yielding contract

Every seam in the row-flow returns `iterable<int, array<string, scalar|null>>`:

- `SqlExecutor::fetchAll(): iterable` — accepts arrays OR generators from implementations.
- `SqlDataSource::fetchRows(): iterable`
- `ReportDataSourceInterface::fetchRows(): iterable` — **widened from `: array`, breaking change**

**Existing `Sqlite*Provider` classes:** return-type widened to `iterable` as part of the retrofit; internal behaviour unchanged (they can keep returning arrays until they're converted to `NamedSqlDataSource` instances). Snapshot tests unaffected — `foreach` iterates arrays and generators identically.

**The library's new abstractions genuinely yield** (Lee's ask "yielding built in for performance"):
- `PdoExecutor`: `while (fetch) yield` — no fetchAll materialisation.
- `NamedSqlDataSource::fetchRows()`: generator wrapping the executor's iterable, applies per-row coercion as it yields.
- `EnrichedDataSource::fetchRows()`: lookup side materialises once (needed for hash join); primary side streams; result is a merging generator.

**Multi-pass safety in `DefinitionFiller::getRowsFor()`** (one-line change):
```php
$this->rowCache[$name] = is_array($rows) ? $rows : iterator_to_array($rows);
```
Preserves the existing "fetch once per source per fill()" invariant even when the source returns a generator. Arrays pass through unchanged.

**Deferred:** true single-pass streaming with concurrent aggregation (walking rows once while accumulating summary/group-footer aggregates in-flight). Requires redesigning `AggregateExpression` around accumulator objects rather than post-hoc row iteration. Filed as follow-up ticket; unblock when a genuinely large-result-set report shows up.

## Atom parameterisation discipline (mini-ADR)

**Decision:** SQL data-source atoms accept parameters covering a *family* of reports, not a single one. Parameter values are **bounded** — enumerations, ranges, allowlisted keys — so no atom parameter ever embeds a SQL fragment.

**Why:** without this discipline, one of two failure modes:
- Atoms take only `:date` and every novel filter/grouping needs a new atom → back to the class-per-report tax.
- Atoms accept arbitrary column names for filter/groupBy → they're SQL fragments in disguise; injection surface and AI-safety story break.

**Concrete parameter idioms:**
- `date` → `YYYY-MM-DD` string (regex-validated, `DateParam::require()`)
- `date_range` → `{start: YYYY-MM-DD, end: YYYY-MM-DD}` (both validated)
- `limit` → `int` in `[1..1000]`
- `sort_dir` → enum `'asc' | 'desc'`
- `bucket` → enum `'hour' | 'day-of-week' | 'week' | 'month'` — bucketing expressions inline in the atom's SQL, keyed by param value

**Non-idioms** (do not adopt):
- `where_column: string` — inline column name from data → SQL fragment
- `group_by: string` — same
- `custom_sql: string` — literally SQL fragment; use `RawSqlDataSource` (future) with safety policy instead

## Framework adapter roadmap

| Adapter | Package | Status |
|---|---|---|
| `PdoExecutor` | `writer/` | Ships in the spike PR |
| `DoctrineDbalExecutor` | `writer/` (decided in-repo) | Near-term follow-up — first real-consumer adapter for Lee's stack |
| `EloquentExecutor` | `report-writer-laravel` (companion) | On demand |
| Test-only executors (in-memory, audit-recording, spy) | consumer-provided | Trivial to author |

**Doctrine placement decided:** in-repo (writer/). Faster iteration for the primary near-term consumer; the ADR-013 spirit is preserved by keeping the executor a thin adapter (~30 LOC) with no framework code in the surrounding library. Doctrine DBAL becomes an optional `require-dev` for tests and a suggests-hint in `composer.json` for consumers who wire it up. If a Laravel consumer ever arrives, `EloquentExecutor` still ships as a separate `report-writer-laravel` companion (Eloquent's transitive deps are heavier and merit isolation).

## Backwards compatibility + retrofit plan

- `ReportDataSourceInterface::fetchRows(): array` widens to `: iterable`. Existing implementations (`Sqlite*Provider` × 4, one hardcoded-rows test fixture) get their signatures updated. No consumer behaviour changes (all consumers use `foreach`).
- `DefinitionFiller::getRowsFor()` gains the `is_array ? : iterator_to_array` line. Behaviour identical for array-returning sources.
- `AggregateExpression`, `Grouping::byField`, `LayoutService` — unchanged. All operate on already-materialised arrays (post-cache).
- Existing JSON templates (`sales-by-category.json`, `open-tabs.json`) — unchanged. Their `"data_source": "name"` references still resolve through the registry to the retrofitted `Sqlite*Provider` instances.
- Existing snapshot tests (`sales-by-category.html`, `open-tabs.html`, `sales-by-category-item.html`) — byte-identical after retrofit (row order preserved by SQL ORDER BY; formatters unchanged; layout unchanged).

**Retrofit path for the existing 4 SqliteXProvider classes:**
1. Spike PR retrofits `SqliteDailySalesProvider` → subclass of `NamedSqlDataSource` (proves the shape).
2. Opportunistic conversion of the remaining three as we touch them in future work (no rush; no functional change from the retrofit).

## Rollout — PR sequencing

**PR 1 — Spike (this spec's core deliverable).** Files touched:
- `writer/src/Interfaces/SqlExecutor.php` (new)
- `writer/src/Interfaces/SqlDataSource.php` (new)
- `writer/src/DataSource/PdoExecutor.php` (new)
- `writer/src/DataSource/NamedSqlDataSource.php` (new)
- `writer/src/Interfaces/ReportDataSourceInterface.php` (return-type widen to `iterable`)
- `writer/src/Fill/DefinitionFiller.php` (one-line materialise-on-cache)
- `writer/src/Builder/ReportBuilder.php` (add `->dataSource()`)
- `writer-app/src/Kernel.php` (wire `PdoExecutor` around existing PDO service)
- `writer-app/src/Reports/DailySalesFiller.php` (retrofit to use `->dataSource(new DailySalesDataSource(...))`)
- `writer-app/src/Reports/DataSource/SqliteDailySalesProvider.php` → renamed and reshaped to `DailySalesDataSource extends NamedSqlDataSource`
- Tests: unit tests for `PdoExecutor`, `NamedSqlDataSource` (including coercion + yielding); `DailySalesFiller` snapshot unchanged
- `CHANGELOG.md` entry — first writer/ change since A2; semver **MINOR** bump (additive interfaces + one widened return type on a public interface; backwards-compat for consumers who call `fetchRows()`, mildly breaking only for external classes that *implement* `ReportDataSourceInterface`)
- Reviewer dispatch (`dry-solid-reviewer` + `security-scanner`) as project canon (agents in `.claude/agents/`)

**PR 2 — `EnrichedDataSource` composite.** ~1 new file + 1 test suite. Adds the first composition primitive. Includes ctor-time validation of `on`/`select` against child `columnSpec`.

**PR 3 — `DoctrineDbalExecutor`.** ~30 LOC + tests. Unlocks Symfony/Doctrine consumers (Lee's real stack).

**PR 4 — Atom parameterisation ADR.** Doc-only. Formalises the discipline as `docs/09-conventions/decisions/014-atom-parameterisation.md`.

**PR 5 — A3.5 (Register Close) pivoted to ReportBuilder-based.** Three atoms (`orders-closed-on-date`, `payments-by-date`, `staff-lookup`) + one `RegisterCloseFiller` (ReportBuilder-based). Follows A3.4's shape; consumes the new SqlDataSource + composition APIs. *(Note: rw2's earlier paused A3.5 draft assumed the JSON-templated pattern; this pivot supersedes it — discard-and-restart, not adapt.)*

**PR 6 — A3.6 (Full Menu Book) pivoted to ReportBuilder-based.** Kitchen-sink report; exercises subreports + splittable text + ComputedExpression + onBand hook + new SqlDataSource + at least one composition primitive if `LeftAntiJoinDataSource` has landed.

## Deferred / out of scope for this spec

The following are architecturally implied by the direction but explicitly *not* part of the ship-list:

- **`Registry::describe()` machine-introspection API.** AI-enablement feature; ships in the 4–8 week runway before the AI integration lands.
- **`RawSqlDataSource` + `SqlSafetyPolicy` interface.** AI-authored SQL escape hatch with allowlist/audit/runtime-bounds enforcement. Ships alongside AI integration; needs its own mini-spec.
- **`PeriodOverPeriodDataSource`, `LeftAntiJoinDataSource`.** Tracked as follow-up composition primitives; unblock when a real report needs them.
- **True single-pass streaming with concurrent aggregation.** Requires rewriting `AggregateExpression` around accumulators. Filed for when a large-result report requires it.
- **UI builder.** Future consumer; API-shape decisions in this spec keep it reachable.
- **Human-facing general SQL query editor.** Explicitly deprioritised — the AI supersedes this need, dev-authored atoms cover the rest.
- **DQL / Eloquent ORM native DataSource impls.** Reports want tabular data; ORMs return object graphs. Consumers who need entity hydration implement `ReportDataSourceInterface` directly (wide seam).

## Test strategy

**Unit — `writer/tests/Unit/DataSource/`:**
- `PdoExecutorTest`: yields rows lazily (assert via generator introspection or by counting fetches against a spy PDO); binds named params correctly; passes SQL through verbatim.
- `NamedSqlDataSourceTest`: coerces per `columnSpec` (int, cents, date, datetime, float, string types); yields; introspection accessors return declared values.
- `EnrichedDataSourceTest` (PR 2): ctor validation fires for unknown join key / unknown select source / colliding alias; hash-join correctness with LEFT default; multi-key `on`; iterator behaviour.

**Unit — `writer/tests/Unit/Fill/`:**
- `DefinitionFillerTest`: existing tests unchanged (arrays); new test verifies iterator-returning source materialises correctly on cache-miss.

**Unit — `writer/tests/Unit/Builder/`:**
- `ReportBuilderTest`: new `->dataSource()` method wired end-to-end; consumes both `SqlDataSource` and `ReportDataSourceInterface` shapes.

**Snapshot — `writer-app/`:**
- `DailySalesFiller` snapshot: byte-identical after retrofit (regression guard).
- All other snapshots (`sales-by-category`, `open-tabs`, `sales-by-category-item`): unchanged (they hit their un-retrofitted providers).

**Reviewer dispatch on the spike PR diff:**
- `dry-solid-reviewer` — flag any residual duplication between the new abstractions and existing `Sqlite*Provider` code.
- `security-scanner` — the new `NamedSqlDataSource` accepts SQL strings via ctor; ensure the value-object doesn't introduce a data-driven SQL sink (the SQL is dev-authored at composition time, not runtime-supplied).

## Twig → ReportBuilder migration recipe (sketch)

For Lee's team's existing pattern (Symfony controller + Doctrine DBAL + Twig template), the migration is roughly:

**Before:**
```php
public function dailySalesReport(Request $req, Connection $db, Environment $twig): Response
{
    $rows = $db->executeQuery(
        'SELECT ... FROM orders o JOIN order_items ... WHERE date(o.closed_at) = :date',
        ['date' => $req->query->get('date')]
    )->fetchAllAssociative();
    return new Response($twig->render('reports/daily-sales.html.twig', ['rows' => $rows]));
}
```

**After:**
```php
public function dailySalesReport(Request $req, DoctrineDbalExecutor $exec, LayoutService $layout, HtmlRenderer $renderer): Response
{
    $source = new NamedSqlDataSource(
        $exec, 'daily-sales',
        'SELECT ... FROM orders o JOIN order_items ... WHERE date(o.closed_at) = :date',
        ['date' => ParamSpec::date('date')],
        ['order_id' => ColumnSpec::int(), 'total_cents' => ColumnSpec::cents(), /*…*/]
    );
    $report = ReportBuilder::create('daily-sales')
        ->title('Daily Sales — {date}')
        ->columns([/* … */])
        ->dataSource($source)
        ->build($req->query->all());
    $stream = $layout->layout($report);
    return new Response($renderer->render($stream));
}
```

Same responsibilities (query + template), different substrate. The gain: fluent template becomes typed + composable + snapshot-testable; `staff-lookup` and other atoms become reusable across reports; AI can author both sides once the bridge lands.

**Migration path decided: cutover** — Lee's team's existing reports get rewritten against `ReportBuilder`; no Twig-compat bridge (a `TwigRenderer` alongside `HtmlRenderer` was considered and rejected). Rationale: the reports need rewriting to gain the framework's benefits anyway (composable atoms, snapshot tests, AI-authorship-ready), so a bridge would just delay the migration for no durable payoff.

Committing to write this recipe as a proper doc lives in `docs/handoff/twig-to-reportbuilder-migration.md` post-A3.

## Non-goals

- **Modifying `HtmlRenderer` or `JsonRenderer`.** They consume `PositionedElement` — data-source shape doesn't reach them.
- **Modifying `LayoutService`.** Layout consumes materialised bands; still works.
- **Modifying `AggregateExpression` semantics.** Multi-pass aggregation preserved via cache-materialisation in `DefinitionFiller`.
- **Adding a new interface to `ReportDataSourceInterface`.** Only the return type widens.
- **A `PageConfig` or physical-layout concern in the data-source layer.** These live in Layout, unrelated to data-source shape.
- **A JSON-template schema change for inline SQL.** Explicitly rejected earlier in the design conversation — templates continue to reference registered atoms by name.

## Open items

*All resolved. Spec is ready for merge.*

- ~~Doctrine adapter placement~~ → **in `writer/`** (see Framework adapter roadmap).
- ~~Twig migration path~~ → **cutover** (see Twig → ReportBuilder migration recipe).
- ~~PHP minimum version~~ → **stays at 7.4** (Lee's work stack). Consequence: no PHP 8 features (named-args, constructor property promotion, readonly, enum types). Code examples in this spec are 7.4-compatible; the *"prefer named-args at ctor sites for AI-readability"* discipline is **dropped** — AI-friendliness comes from small interfaces + descriptive positional-arg names + `paramSpecs()`/`columnSpec()` introspection instead.
