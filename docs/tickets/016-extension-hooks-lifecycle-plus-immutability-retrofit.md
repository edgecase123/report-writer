# TICKET-016: Add lifecycle hooks to ReportBuilder + DefinitionFiller (immutable-fluent), retrofit `onBand` immutability

**Status:** ✅ Closed (2026-08-23) — shipped via PR #9 (`03634a2`)
**Priority:** Medium (unblocks host apps that want lifecycle transforms without subclassing the shipped fillers)
**Source:** design session 2026-08-23 — the "collapse user-scripting to hooks + named strategies" pivot
**Scope:** `writer/src/Builder/ReportBuilder.php`, `writer/src/Fill/DefinitionFiller.php`, `writer/tests/**/*.php`

## Problem

The pipeline needs a way for host apps to intercept the fill and build stages without subclassing the shipped fillers. Concrete use cases:

- Normalize params before the data source is queried (`beforeFill`)
- Mutate the built `ReportInstance` before returning it — remove empty bands, inject rows, reorder (`afterFill` / `afterBuild`)
- Transform rows before `ReportBuilder` processes them (`beforeBuild`)
- Suppress or replace individual bands after they're built (`onBand` — exists on `DefinitionFiller` today; missing from `ReportBuilder`)

Today the only hook available is `DefinitionFiller::onBand`. Callers with these needs must subclass the filler or wrap it — both invasive, both spread lifecycle logic across host-app class hierarchies instead of keeping it in composable callables.

## Proposed fix

Two related deliverables landing in one PR.

### 1. Retrofit `DefinitionFiller::onBand` to true immutability

A1 [Ticket 006](006-definitionfiller-onband-immutability.md) closed the "onBand claims fluent but mutates" problem by dropping the fluent return (`: void`). That was correct at the time. Under the immutable-fluent convention codified in `docs/architecture/extension-hooks.md`, the honest fix is:

```php
public function onBand(string $bandId, callable $callback): self
{
    $clone = clone $this;
    $clone->bandCallbacks[$bandId][] = $callback;
    return $clone;
}
```

PHP's default clone is shallow; the `bandCallbacks` property is an array of arrays of callables. Arrays are value types in PHP, so the shallow clone gives an independent `bandCallbacks` on the clone — no manual deep-clone gymnastics needed. Verify with a test.

Every existing `onBand` call site under `writer/tests/**` (and any A2 code that ends up touching `onBand`) must be rewritten:

```diff
-$filler->onBand('detail', $cb);
+$filler = $filler->onBand('detail', $cb);
```

Fire-and-forget calls silently drop the callback under the new semantics — the mutation happens on the clone, which is discarded. Grep every call site and update.

### 2. Add new lifecycle hooks

Per `docs/architecture/extension-hooks.md`:

**`ReportBuilder`:**
- `beforeBuild(callable(array $rows): array): self`
- `afterBuild(callable(ReportInstance): ReportInstance): self`
- `onBand(string $bandId, callable(BandInstance, BandContext): ?BandInstance): self` — mirror of `DefinitionFiller::onBand` semantics (null return suppresses)

**`DefinitionFiller`:**
- `beforeFill(callable(array $params): array): self`
- `afterFill(callable(ReportInstance): ReportInstance): self`

Each method clones `$this`, appends to the cloned callback array, returns the clone. Multiple registrations of the same hook chain in registration order:

```php
$params = array_reduce(
    $this->beforeFillCallbacks,
    fn ($acc, $cb) => $cb($acc),
    $params
);
```

Callback signatures declare non-nullable return types (`: array`, `: ReportInstance`). If a callback wants to no-op, it returns the input unchanged. A callback that forgets its `return` — or explicitly returns `null` — raises `TypeError` at the next reducer step. Loud failure preferred over silent no-op. The only hook that legitimately returns `null` is `onBand`, where `null` means "suppress this band" — that semantic is load-bearing.

Callbacks that throw propagate up through `build()` / `fill()`. No try/catch in the reducer.

## Acceptance criteria

- [ ] All 5 new methods present with the exact signatures above
- [ ] `DefinitionFiller::onBand` returns `: self` and is truly immutable — a unit test verifies that after `$b = $a->onBand(...)`, the original `$a->bandCallbacks` is unchanged
- [ ] `ReportBuilder::onBand` mirrors `DefinitionFiller::onBand` (null return suppresses the band)
- [ ] Every existing `onBand` call site under `writer/tests/**` reassigns the return
- [ ] `writer/`'s existing PHPUnit suite green
- [ ] New unit tests for each new hook covering: (a) receives correct input, (b) returned value flows through, (c) chaining (multiple registrations invoke in order, each seeing previous output), (d) throwing callback propagates up, (e) `onBand` null-return still suppresses on both classes
- [ ] `docs/architecture/extension-hooks.md` updated if any signature deviation from the current draft

## Non-goals (for this ticket)

- `beforeLayout` / `afterLayout` / `beforeRender` / `afterRender` on any class — deferred per the Fill/Layout separation invariant (fillers must never learn about layout math). If layout/render hooks are ever needed, they land on `LayoutService` / `HtmlRenderer` in a separate ticket.
- `HooksTrait` for custom `ReportFillerInterface` implementations — YAGNI. Extract only when a second custom filler reimplements the same shape.
- Null-as-no-op sugar for lifecycle hooks — dropped in design (`return $input` covers it more explicitly).

## Related

- Supersedes: [Ticket 015](015-implement-user-scripting.md) — user-scripting design pivoted 2026-08-23 to hooks + named strategies; user-scripting.md marked superseded, extension-hooks.md replaces it
- Partially reverses: [Ticket 006](006-definitionfiller-onband-immutability.md) — closed in A1 by dropping fluent return to `void`; now the honest fix (true immutability with `: self`) is applied
- Design spec: `docs/architecture/extension-hooks.md`

## Resolution

Shipped 2026-08-23 via PR #9 (merge commit `03634a2`). Both `ReportBuilder`
and `DefinitionFiller` gained 5 lifecycle hooks (`beforeFill`/`afterFill`
on filler; `beforeBuild`/`afterBuild` on builder; `onBand` on both) with
the immutable-fluent convention. `DefinitionFiller::onBand` was retrofitted
from `: void` back to `: self` with clone-and-return semantics (properly
immutable this time — supersedes Ticket 006's stopgap). 36 new tests added.
Security docblocks (R5: code-only API, never construct callables from data)
were added in a follow-up commit `f900919`.
