# TICKET-015: Implement user-scripting per `docs/architecture/user-scripting.md`

**Priority:** Epic — hard future requirement
**Source:** session-design (2026-08-22) — explicit user requirement
**Status:** ❌ Superseded (2026-08-23) — design pivoted from user-authored scripting to host-app hooks + named strategies. See `docs/architecture/extension-hooks.md` and [Ticket 016](016-extension-hooks-lifecycle-plus-immutability-retrofit.md). `docs/architecture/user-scripting.md` retained for threat model + rejected-mechanism reference.
**Scope:** New sandbox runtime (PHP + TS), extension seams across `writer/src/`, JSON template schema additions, `writer-app/scripts/` directory convention, Registry additions

## Problem

Extension seams in the pipeline (data sources, formatters, band hooks, computed content) currently accept PHP callables set in code only. The user-scripting doc at `docs/architecture/user-scripting.md` specifies a hard future requirement to allow PHP or TypeScript scripts to be attached to these seams, from both backend and frontend/template surfaces, with sandboxing.

Two paths, both in scope:

- **Backend path** (higher trust, full capability) — scripts in `writer-app/scripts/*` referenced by imports; can define data sources, formatters, hooks, param transforms, post-render transforms
- **Frontend/template path** (lower trust, restricted context) — scripts inline in JSON templates or imported from the pool; can invoke anything backend has made available to the report but cannot make network calls, access filesystem, or reach data sources not registered for the report

Explicit imports (`imports:` block in the template), not auto-discovery. Trust boundary at "what backend has made available to this report."

## Blockers

- **Sandbox direction not chosen.** Five candidates in the spec:
  1. Custom expression language (safest, doesn't satisfy "PHP or TypeScript" literally)
  2. Whitelisted PHP AST subset via `nikic/PHP-Parser` + tree-walking interpreter
  3. QuickJS-in-PHP for TypeScript
  4. Deno sidecar (pooled long-lived process, native TS, strongest isolation) — currently the strongest primary-path candidate
  5. WebAssembly (Wasmtime/Wasmer) — plausible in 2027+, too early today

Choosing requires its own brainstorming session comparing implementation complexity, deployment friction, per-invocation latency, and operational maturity of each.

## Deliverable

Ultimately: sandbox runtime + `ScriptResolver` (import specifier interpretation) + `ScriptDiscoverer` (per-render script loading) + JSON schema additions (`computed` content type, `imports:` block, `scripts:` array on bands) + `context` accessor injected into sandbox environment + security-scanner Rule R5 update.

Not writable in one PR — likely splits into: (a) sandbox runtime + one path (backend or frontend); (b) the other path; (c) JSON schema + template additions; (d) capability injection layer + context accessor.

## Notes

- Design captured in `docs/architecture/user-scripting.md` — that document is the source of truth for the requirement, not this ticket
- Related: `.claude/agents/security-scanner.md` Rule R5 forbids callable-from-data today; that rule gets updated when this ticket implements the sandboxed path
- Absolutely not in scope for Sub-project A — flag any leakage
- Registry-of-named-strategies workaround (backend PHP registers `ComputedExpression` variants and templates reference by name) is the interim path until this lands
