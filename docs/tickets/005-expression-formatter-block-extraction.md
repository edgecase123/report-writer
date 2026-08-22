# TICKET-005: Extract shared formatter block across Expression classes

**Priority:** Low
**Source:** dry-solid-reviewer audit (2026-08-22) — 🟡 DRY (renderer boilerplate)
**Scope:** `writer/src/Expression/FieldExpression.php`, `AggregateExpression.php`, `ComputedExpression.php`

## Problem

Each of the three concrete `ContentExpression` implementations carries:

1. A `private $formatter` field of type `?callable`
2. A `withFormatter(callable): self` clone-on-write mutator
3. A trailing `if ($this->formatter !== null) { return (string) ($this->formatter)($value); } return (string) $value;` block at the end of `evaluate()`

Three copies of the same shape.

## Proposed fix

Two options:

**Option A — Abstract base class** (`AbstractFormattableExpression`):

```php
abstract class AbstractFormattableExpression implements ContentExpression
{
    /** @var callable|null */
    protected $formatter;

    public function withFormatter(callable $fn): self
    {
        $clone = clone $this;
        $clone->formatter = $fn;
        return $clone;
    }

    protected function applyFormatter($value): string
    {
        if ($this->formatter !== null) {
            return (string) ($this->formatter)($value);
        }
        return (string) $value;
    }
}
```

Concretes extend and call `$this->applyFormatter($value)` at the end of `evaluate()`.

**Option B — Trait** (`FormattableExpressionTrait`) — same members, mixed in where needed.

## Acceptance criteria

- [ ] Decision made and documented (A vs B) — abstract class is more conventional and enforceable; trait is lighter
- [ ] Shared code lives in one place
- [ ] Each concrete `evaluate()` ends with one call to the shared apply
- [ ] Full phpunit suite passes

## Notes

- Recommend Option A — inheritance is the right shape for "these expressions ARE formattable" and Trait use for shared behavior is less enforceable at the type level.
- `StaticExpression` doesn't need this since it has no formatter (it's a literal string). Leave it as a plain `ContentExpression` implementation.
- Can land in the same PR as [Ticket 001](001-aggregate-math-dry-consolidation.md) since both touch `AggregateExpression`.
