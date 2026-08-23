---
title: Extension Hooks and Named Strategies
updated: 2026-08-23
status: design decided; hook API locked (Ticket 016 tracks implementation)
supersedes: user-scripting.md
---

## Model

One trust boundary: **host-app PHP code**. No user-authored text scripts, anywhere.

- **Backend hooks.** `ReportBuilder`, `DefinitionFiller`, and any custom filler class expose named lifecycle hooks. Host code registers PHP callables; the library invokes them at the right lifecycle point. Full PHP capability; the callables ARE host code.
- **Named strategies from JSON.** Templates reference strategies by name (e.g. `"format": "currency"`). Names resolve at fill time via code-registered Registries (`FormatterRegistry` today; add more as needed). Templates remain pure data.

There is no separate frontend scripting layer. Interactivity in the viewer app (zoom, page jump, toggles, live preview in the Builder) is built as ordinary Vue components inside the viewer — not as scripts authored per report.

## Why this shape

- No sandbox to build; no `eval` / AST walker / WASM / QuickJS / subprocess pool
- Trust surface stays at "host-app developers" — same as everything else the host runs
- Adding a new strategy = ship PHP + redeploy, which is exactly the friction level a reporting library inside a larger app should carry
- Snapshot tests stay deterministic because everything is code-driven
- Print output remains byte-identical to screen because no JS mutates the DOM after render

## Backend hooks — locked API

Concrete implementation tracked as [Ticket 016](../tickets/016-extension-hooks-lifecycle-plus-immutability-retrofit.md).

### Convention: immutable-fluent

All hook-registration methods on both `ReportBuilder` and `DefinitionFiller` follow the immutable-fluent convention:

- Signature returns `: self`
- Implementation clones `$this` before mutating any state
- Returns the clone

Callers MUST reassign: `$filler = $filler->onBand('detail', $cb)`. Fire-and-forget `$filler->onBand(...)` silently drops the callback because the mutation happens on a discarded clone.

Consistency rationale: `ReportBuilder` has always been immutable-fluent (`title()`, `columns()`, `rows()`, etc. all clone-and-return). `DefinitionFiller::onBand` was refactored to `: void` in A1 ([Ticket 006](../tickets/006-definitionfiller-onband-immutability.md)) as a hasty fix for the "claims fluent, actually mutates" bug. The right long-term fix — codified here and tracked by [Ticket 016](../tickets/016-extension-hooks-lifecycle-plus-immutability-retrofit.md) — is to make it truly immutable so `: self` becomes honest again.

### ReportBuilder

| Hook | Signature | Fires | Semantics |
|---|---|---|---|
| `beforeBuild` | `beforeBuild(callable(array $rows): array): self` | Before `build()` constructs bands from rows | Chain: each callback receives previous output |
| `afterBuild` | `afterBuild(callable(ReportInstance): ReportInstance): self` | Before `build()` returns | Chain: each callback receives previous output |
| `onBand` | `onBand(string $bandId, callable(BandInstance, BandContext): ?BandInstance): self` | After each band is built | Chain; `return null` suppresses the band |

### DefinitionFiller

| Hook | Signature | Fires | Semantics |
|---|---|---|---|
| `beforeFill` | `beforeFill(callable(array $params): array): self` | Before data source is queried | Chain: each callback receives previous output |
| `afterFill` | `afterFill(callable(ReportInstance): ReportInstance): self` | Before `fill()` returns | Chain: each callback receives previous output |
| `onBand` | `onBand(string $bandId, callable(BandInstance, BandContext): ?BandInstance): self` | After each band is built (existing today; retrofit to true immutability per Ticket 016) | Chain; `return null` suppresses the band |

### Reducer

Multi-callback chaining uses `array_reduce`:

```php
$params = array_reduce(
    $this->beforeFillCallbacks,
    fn ($acc, $cb) => $cb($acc),
    $params
);
```

### Return-type strictness

Lifecycle hook signatures declare non-nullable return types (`: array`, `: ReportInstance`). If a callback wants to no-op, it returns the input unchanged (`return $params`). A callback that forgets its `return` — or explicitly returns `null` — raises `TypeError` immediately. Loud failure preferred over silent no-op.

The only hook that legitimately returns `null` is `onBand` (both classes), where `null` is a load-bearing signal meaning "suppress this band". Reserving `null` semantics to `onBand` keeps its meaning unambiguous across the API surface.

### Error propagation

Callbacks that throw propagate up through `build()` / `fill()`. No try/catch in the reducer — host-code bugs should surface as exceptions, not silent swallows.

### What's deliberately NOT on ReportBuilder / DefinitionFiller

- `beforeLayout` / `afterLayout` — belong on `LayoutService`, not the fillers. Per the Fill/Layout separation invariant (see `CLAUDE.md`), fillers must never learn about layout math. Adding layout hooks to fillers would breach that seam.
- `beforeRender` / `afterRender` — belong on `HtmlRenderer` / `JsonRenderer`, not the fillers. Same seam concern.
- Both remain unimplemented until the "same pre-layout transform across N reports" pattern actually shows up in a real consumer. YAGNI.

### Extension for custom fillers

A custom class implementing `ReportFillerInterface` directly (not extending `ReportBuilder` or `DefinitionFiller`) doesn't get free hooks. It owns its own lifecycle. If it wants hooks, it implements the same pattern — clone-and-return registration methods, `array_reduce`-driven invocation.

If a `HooksTrait` becomes worth extracting because two or more custom fillers reimplement the same shape, extract then. Not before.

## Named strategies from JSON templates

Existing pattern (unchanged):

```json
{ "content": { "type": "field", "field": "amount", "format": "currency" } }
```

`"currency"` is a lookup key; the callable behind it is populated in PHP code (`FormatterRegistry::defaults()` today).

Extension pattern: when a template needs a new capability, ship a new named strategy in code, redeploy, templates can reference it. For example, adding computed expressions from JSON:

1. Add a `ComputedExpressionRegistry` keyed by name
2. Populate in code: `$registry->set('percent-change', fn ($ctx) => …)`
3. Templates reference by name: `{ "content": { "type": "computed", "using": "percent-change" } }`

Rule R5 stays enforced: JSON never maps to callable source, only to a name.

## Non-goals

- User-authored text scripts (backend or frontend)
- `ScriptRuntime` abstraction or pluggable script languages
- Sandbox mechanisms (AST whitelist, WASM, QuickJS, subprocess pool, Deno)
- Template `imports` block
- Any frontend scripting surface

## Reference material

`user-scripting.md` retains the threat model, sandbox mechanism analysis, and rejected-alternatives discussion from the earlier design that pursued user-authored scripting. Kept for reference; not the plan.
