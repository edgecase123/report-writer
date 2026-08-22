# TICKET-001: Consolidate aggregate math (DefinitionFiller ↔ AggregateExpression)

**Status:** ✅ Closed (2026-08-22, commit `28827a1`, A1 plan Task 1)
**Priority:** High
**Source:** dry-solid-reviewer audit (2026-08-22) — 🔴 DRY + 🔴 OCP
**Scope:** `writer/src/Fill/DefinitionFiller.php`, `writer/src/Expression/AggregateExpression.php`

## Problem

The sum/avg/min/max/count math is implemented twice, in two files, both keyed by the same `$fn` string:

- `writer/src/Fill/DefinitionFiller.php:285-300` (`computeAggregate`)
- `writer/src/Expression/AggregateExpression.php:40-53` (`compute`)

Both files also carry the same `switch ($fn)` on an aggregate-function-name string — an OCP smell (subtype-shaped enum switched on outside its intended sink).

## Proposed fix

Have `DefinitionFiller::resolveElement()` delegate to `AggregateExpression` instead of re-implementing the switch:

```php
// In DefinitionFiller::resolveElement(), case 'aggregate':
$expr  = new AggregateExpression($content->getFn() ?? 'sum', $content->getField() ?? '');
$raw   = $expr->evaluate(new EvalContext([], $aggregateRows, []));
$value = $this->applyFormat($raw, $content->getFormat());
```

Then delete `DefinitionFiller::computeAggregate()`. Kills both DRY and OCP hits in one edit.

If `AggregateExpression::evaluate()` returning `string` (with formatter applied) vs `computeAggregate` returning `float` matters for downstream call shape, add `AggregateExpression::computeRaw(): float` and share that.

## Acceptance criteria

- [ ] `DefinitionFiller::computeAggregate()` is removed
- [ ] Aggregate handling in `DefinitionFiller::resolveElement()` delegates to `AggregateExpression`
- [ ] Full phpunit suite passes (`composer install && vendor/bin/phpunit`)
- [ ] No behavior change in test snapshot output for grouped or summary reports

## Notes

- Second highest-priority DRY finding from the audit (first was the `groupRows` extraction, already landed via `Instance/Grouping.php`).
- Related: Ticket 005 (Expression formatter block extraction) can land in the same PR since it touches `AggregateExpression`.
