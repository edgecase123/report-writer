# TICKET-006: Fix `DefinitionFiller::onBand` fluent-returns-but-mutates

**Priority:** Medium
**Source:** dry-solid-reviewer audit (2026-08-22) — 🟡 Builder immutability breach
**Scope:** `writer/src/Fill/DefinitionFiller.php:48-52`

## Problem

`DefinitionFiller::onBand()` returns `self` (implying fluent-immutable) but mutates `$this->bandCallbacks[$bandId][] = $callback` in place. Any caller doing `$configured = $filler->onBand(...)` while holding `$filler` will see the earlier reference change under them.

The library's canonical fluent-immutable shape (`Builder/ReportBuilder`) does clone-on-write on every setter. Adjacent code shouldn't set a bad precedent.

## Proposed fix

Two options:

**Option A — Drop fluent** (recommended for simplicity):

```php
public function onBand(string $bandId, callable $callback): void
{
    $this->bandCallbacks[$bandId][] = $callback;
}
```

Callers just call it, no chaining. Breaks any current chaining callers (there don't appear to be any inside the repo).

**Option B — Clone-on-write** (preserves fluent):

```php
public function onBand(string $bandId, callable $callback): self
{
    $clone = clone $this;
    $clone->bandCallbacks[$bandId] = array_merge(
        $clone->bandCallbacks[$bandId] ?? [],
        [$callback]
    );
    return $clone;
}
```

Also requires marking the class as immutable-by-convention and reviewing whether internal `$this->` mutations elsewhere (e.g. `$this->rowCache` in `fill()`) violate that.

## Acceptance criteria

- [ ] Decision documented (A vs B)
- [ ] `DefinitionFiller::onBand()` no longer has misleading return type
- [ ] All existing callers still work
- [ ] Full phpunit suite passes

## Notes

- Recommend Option A. `DefinitionFiller` is stateful during a `fill()` call (row cache); it's not a Builder in the immutable sense. Dropping the fluent return is honest.
- Watch for any tests or examples using `$filler->onBand(...)->onBand(...)` chaining — grep before changing.
