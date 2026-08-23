---
title: Extension Hooks and Named Strategies
updated: 2026-08-23
status: design decided; concrete hook list is a candidate proposal
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

## Backend hooks — candidate lifecycle points

Existing today:

- `DefinitionFiller::onBand(string $bandId, callable $cb): void` — post-band-build hook. Return `null` to suppress the band, or return a (possibly modified) `BandInstance` to override. Multiple callbacks per band chain in registration order.

Candidates to add (final API TBD; each proposed as a method on the builder / filler surface):

| Hook | Receives | Returns | Fires |
|---|---|---|---|
| `beforeFill(callable)` | `array $params` | `array` (mutated params) | before the data-source query, once per fill |
| `afterFill(callable)` | `ReportInstance` | `ReportInstance` (mutated) | after all bands built, before returning from `fill()` |
| `beforeLayout(callable)` | `ReportInstance` | `ReportInstance` | before `LayoutService::layout()` |
| `afterLayout(callable)` | `ReportStream` | `ReportStream` | after `LayoutService::layout()`, before rendering |
| `beforeRender(callable)` | `ReportStream` | `ReportStream` | before renderer runs |
| `afterRender(callable)` | `string $output` | `string` (mutated) | after renderer produces output |

Per-band hooks (`onBand`-style) stay filler-scoped because they need band-lifecycle context. The lifecycle hooks above live on the builder (per-report) and on the filler (per fill invocation) — same shape either way, both hand back mutated versions of what they were passed.

Hook chaining: multiple callables per hook, invoked in registration order; each receives the output of the previous. Matches `DefinitionFiller::onBand`'s existing behavior.

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
