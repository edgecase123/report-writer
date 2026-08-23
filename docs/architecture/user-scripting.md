---
title: User Scripting in Band Hooks and Content Expressions (future direction)
updated: 2026-08-23
status: language space decided (frontend JS/TS; backend PHP or JS/TS via pluggable ScriptRuntime, PHP-only in v1); sandbox mechanism TBD
scope: future major version; explicitly OUT OF SCOPE for Sub-project A (the standalone build)
---

> **Status.** Hard requirement for a future version, per user direction 2026-08-22. Not designed. Not implemented. Not on the immediate roadmap. This document exists so the requirement doesn't get lost, so the design surface is thought about before code decisions foreclose options, and so anyone reading the security-scanner agent's Rule R5 ("never map JSON → PHP callable") knows the rule is a *current* rule with a planned safe path around it, not a permanent architectural veto.

---

## The requirement

Allow **executable scripts** to be attached to any extension point in the pipeline. Both **backend-authored** and **frontend/template-authored** scripts are in scope (per user direction 2026-08-22).

**Runtime shape:**

- **Frontend/template scripts** are JavaScript or TypeScript and execute in a JavaScript engine (browser at page-render time, Node during Vite dev).
- **Backend scripts** execute via a pluggable `ScriptRuntime` abstraction the host wires in at composition-root time. The abstraction supports two script languages: PHP or JavaScript/TypeScript. The **v1 concrete implementation ships PHP only** — the demo host is PHP, so a PHP-in-PHP sandbox is the lowest-friction starting point. A JavaScript backend runtime (embedded QuickJS, WASM, or Node subprocess — see Design Directions) can be added as a second implementation later without pipeline changes.

What differs across paths is the **capability grant** — the set of objects and functions injected into the sandbox environment, plus where scripts execute (server vs browser).

Backend-authored scripts get the full pipeline API (register data sources, register formatters, install band hooks, transform params before fill). Frontend/template-authored scripts get a **restricted context** — read access to the current report's rows, aggregate rows, params, and registered data sources, plus safe stdlib (math, string manipulation, date parsing, JSON). Neither path grants `fetch()`, filesystem, or process access to script code.

### Backend path — higher trust, full capability, imported explicitly

Backend scripts live in the filesystem — inside the host app's codebase, inside an installed package, or in an admin-configured scripts base directory — but **nothing auto-registers on boot**. A report activates a script by naming it in an `imports` block in the report's definition (JSON template, `ReportBuilder` code, or custom filler class — all three fill paths support imports).

```json
{
  "report_definition_id": "daily-sales",
  "data_source": "daily_sales",
  "imports": {
    "usd":            "@builtin/formatters/currency",
    "percent_change": "@edgecase/reporting-extras/formatters/percent-change",
    "hide_zero":      "./scripts/hooks/hide-zero-rows",
    "company_fmt":    "./scripts/formatters/company-currency"
  },
  "bands": [
    {
      "id": "detail", "type": "detail",
      "scripts": [{ "phase": "post-build", "call": "hide_zero" }],
      "elements": [
        { "id": "amount", "x": 490, "width": 82, "align": "right",
          "content": { "type": "field", "field": "amount", "format": "usd" } }
      ]
    }
  ]
}
```

**Resolution rules for import specifiers** (final syntax TBD; this is the intent):

- `@builtin/…` — shipped-with-library strategies (currency, cents, integer, date formatters; the six standard aggregate functions). These are the vocabulary that lived in `FormatterRegistry::defaults()` today; importing them makes their presence in the template explicit rather than implicit.
- `@vendor/package/…` — from an installed composer or npm package that ships importable strategies. Analogous to `import { foo } from '@vendor/package'` in ES modules or `use Vendor\Package\Foo` in PHP.
- `./relative/path` — from the host app's configured scripts base directory. Path resolution is deterministic and validated against the base (no `..` escapes).
- Bare names (`foo`, `some-hook`) are **not supported** — always explicit, no magic.

The local alias (LHS of the map) is scoped to this report. Two reports can import the same script under different aliases, and two reports can independently use the same alias for different scripts. Within a single report, however, each alias must be unique — reusing an alias in the same `imports` block is a template error.

**Trust context.** The author of a report template controls what scripts run for that template — but only from the pool of scripts that exist in the codebase. Adding a new importable script to that pool requires server-level access (dropping a file, installing a package). A hostile template author can only import scripts that are already installed; they can't inject new code.

The sandbox around backend scripts is **defense-in-depth**, not the primary defense. Backend scripts get a broad capability set including the ability to *define* data sources (which involves I/O to whatever store the data source reads from — DB, filesystem, HTTP). The sandbox catches "a script that infinite-loops" or "a script that panics on unexpected input"; the primary defense against hostile backend scripts is that only trusted people can put files in the pool.

**Why explicit imports instead of directory-scanning discovery:**

- **Isolation.** A bug in a script affects only reports that imported it, not every report in the system.
- **Discoverability.** Reading a report's template tells you exactly which scripts it depends on. No hidden "boot registered a global hook for band 'detail'."
- **Testability.** A test of one report loads only the scripts that report imports. No system-wide side effects.
- **Reuse without pollution.** Same script can be imported by many reports; adding a script to the pool doesn't affect reports that didn't import it.
- **Determinism.** Two identical templates render identically regardless of what other scripts exist in the pool. Determinism is a core property of the pipeline; auto-discovery would break it (the render depends on what happens to be in the filesystem at boot).
- **Versioning surface.** Once imports are explicit, they can carry version constraints later (`"usd": "@builtin/formatters/currency@^2"`). Auto-discovery has nowhere to put version info.

The shipped vocabulary (`FormatterRegistry::defaults()`, the aggregate-function switch in `DefinitionFiller::computeAggregate()`) stays implicit — those are the pipeline's *primitives*, not scripts. Anything that IS a script requires an import.

### Frontend/template path — lower trust, restricted context

A report author editing a JSON template through the builder UI embeds a script inline in the template body. Trust context: whoever the outer app allows to POST to `/api/preview` or `/api/drafts` — in a foreUP-style outer app, authenticated operators; in a public-demo deployment, anyone with browser access.

Template-authored scripts CAN:

- Read the current row (whatever the enclosing band iterates over)
- Read the aggregate rows for the band's scope
- Read the params passed to `fill()`
- Read from the **named data sources already registered for this report** — e.g. `context.dataSource('sales_by_category').rows()` returns the same rows the report is rendering, no new query, no arbitrary data-source access
- Use safe stdlib: string manipulation, arithmetic, date parsing, JSON serialization
- Return a scalar (string, number, bool, null) that becomes the band/element output

Template-authored scripts CANNOT:

- Make network calls of any kind — no `fetch`, `curl_*`, `Http::*`, `XMLHttpRequest`, no third-party API retrieval, no calls back to the host app's own API
- Access data sources NOT registered for the current report (can't reach into other reports' or other tenants' data)
- Access the filesystem, environment, process, or any I/O primitive
- Register new data sources, formatters, or hooks (that's the backend path's job)
- Access other bands' data or other reports' rows
- Persist state across invocations

The capability restriction — **read-only access to the reporting context, no external I/O** — is what makes template scripting shippable alongside the backend path without a hardened per-invocation isolation story. A script that can only see what the report already loaded can't exfiltrate anything the request wasn't already going to expose in the rendered output.

**Language options per path:**

- **Frontend/template:** JavaScript or TypeScript. TypeScript is the recommended authoring form (transpiles cleanly to JavaScript, which is what actually executes in the browser or Node). A host-app config setting can restrict input to one — e.g. `scripts.frontend.language = "typescript"` to require types on all template scripts.
- **Backend:** determined by the concrete `ScriptRuntime` implementation the host wires in. The abstraction accepts either PHP or JavaScript/TypeScript source. **v1 ships one implementation: `PhpScriptRuntime`** — accepts PHP source, executes it in a PHP-in-PHP sandbox (see Direction 2 in Design Directions for the leading candidate mechanism). A future `JsScriptRuntime` would accept JavaScript/TypeScript source and execute it in an embedded JS engine (see Directions 3, 4, or 5). A host is expected to wire exactly one backend runtime; mixing PHP and JavaScript backend scripts within one deployment is not a supported shape.

The sandbox design must cover whichever runtime is wired in, with equal rigor.

The `ScriptRuntime` abstraction exists specifically so PHP-first v1 doesn't foreclose a JS/TS backend option later. Every reference to "the backend script runtime" in library code should go through the interface, never a concrete class name.

### Extension points that could accept scripts

The pipeline has extension seams at multiple stages. Each seam has two questions: *who can author a script for it*, and *who can invoke or reference a script that's been made available*. **The rule for invocation is the same everywhere: anything the backend has made available to a report can be referenced or scripted-against from that report's template.** The authoring rule differs by seam because authoring requires whatever capabilities the seam's contract needs (I/O for a data source, pure computation for a formatter).

| Stage | Seam today | Script form | Where AUTHORED | Where INVOKED / REFERENCED |
|---|---|---|---|---|
| Pre-fill | none (params flow straight into `fill()`) | Param transform: `(params, context) → params` | Backend (I/O may be needed) | Report opt-in via imports; template can name it, script against transformed params |
| Fill — data source | `ReportDataSourceInterface::fetchRows(params): rows[]` | Full data source implementation | Backend (data sources are I/O by definition) | Report opt-in via `data_source` field; template can name it, script against rows it returns |
| Fill — computed content | `ComputedExpression($fn)` (code only today) | Cell value: `(row, aggregateRows, params) → scalar` | **Both** — frontend-authored runs restricted context | Both — template `computed` content type, or invoked by name if imported |
| Fill — formatter | `FormatterRegistry::register(name, callable)` | Value formatter: `(value) → string` | **Both** — frontend-authored is pure function on input | Both — referenced by name in `format:` field |
| Fill — band callback | `DefinitionFiller::onBand($bandId, callable)` | Band transform: `(band, context) → band | null` | **Both** | Both — `scripts:` array on band definition or inline |
| Post-layout | none today | Stream transform: `(stream) → stream` | Backend only | Backend attaches per-report; not directly referenced from template body |
| Post-render | none today | Output transform: `(html or json) → same` | Backend only, cautiously | Same as above |

Rule of thumb for authoring: seams that need I/O to fulfill their contract (data sources, param transforms with lookups, post-layout/render rewrites) can only be authored by backend code — the frontend sandbox never grants I/O. Seams that transform values already inside the pipeline (computed content, formatters, band callbacks) can be authored either way, with frontend-authored scripts running under the restricted-context capability grant.

Rule of thumb for invocation: *anything the backend has made available to the report can be referenced from the template*, regardless of what type of script it is or who authored it. If a report has data source `sales_by_category` in its `imports:` or its top-level `data_source:` field, the report was already going to read those rows during rendering — allowing a template inline script to reference `context.dataSource('sales_by_category').rows()` doesn't expose anything the report doesn't already have access to. Same logic for hooks, formatters, param transforms: the grant is at the "backend blesses this script for this report" line, not at the "who authored it" line.

The current library implements only the "code-authored PHP callable" version of the seams where "Script form" is populated. This spec extends every populated seam with an optional script-authored path.

**Why this matters.** The whole point of the extensible-pipeline shape is to let people change report behavior without editing library code. Today the extension seams accept PHP callables — meaning "extending" requires a code deploy and, for the outer app's operators, a developer. Scripting turns those seams into surfaces reachable from higher levels:

- **Template-authored scripts** let operators express logic that isn't in the shipped vocabulary — "hide rows where the amount is negative and it's a weekday," "compute a derived total from three columns," "highlight items whose category name starts with X."
- **Backend-authored scripts** let server-side developers add hooks and formatters as files rather than as code changes to registered classes — a `writer-app/scripts/formatters/percent.ts` file that appears on disk is registered on next boot without touching `Container.php`.

Without user scripting, extending the pipeline requires PHP class changes + a code deploy for every new hook, formatter, or data source. With it, the pipeline becomes a programming surface — with everything that implies about safety.

---

## Current state (2026-08-22)

- `DefinitionFiller::onBand($bandId, callable)` accepts arbitrary PHP callables. The security surface: any code that constructs the filler can pass any callable. This is safe *because* only code constructs the filler — no data path constructs one today.
- `ComputedExpression` similarly accepts an arbitrary PHP callable. Same safety property: only code constructs it.
- The JSON template intentionally omits any content type that would require callable-from-data construction. See `writer/src/Fill/DefinitionFiller.php::resolveElement()` — no `case 'computed':` branch. The security-scanner agent's Rule R5 states this explicitly: *"never map JSON → PHP callable; if templates need custom expressions, use ComputedExpression exclusively from code and expose a named strategy in a Registry that the JSON references by name."*
- The Registry-of-named-strategies workaround is what shipping code will use up until user scripting lands: a `ComputedExpressionRegistry` keyed by name, populated in PHP, JSON templates reference by name. This works but requires a code deploy for every new named strategy — exactly the friction user scripting exists to remove.

---

## Threat model

### Primary path (backend-authored scripts) — reduced threat surface

An attacker would need **server-level access** to drop a hostile file into `writer-app/scripts/*`. If they have that, they have far bigger vectors than the report engine — they can edit `Container.php` directly, install a Composer package, modify PHP source. The sandbox for backend-authored scripts exists to protect against **accidental** harm (an admin's misconfigured script infinite-loops the render process, a formatter that panics on unexpected input takes down every report using it), not against a hostile author.

Concrete backend-path threats worth sandboxing:

- **Runaway loops or memory** — a hook script with `while(true)` shouldn't hang the request thread.
- **Uncaught exceptions** — a script that throws shouldn't crash the render; the affected band or element should error-out gracefully.
- **Leaked state between invocations** — a script that stashes something in a global shouldn't affect the next invocation of a different report.
- **File / network / process access from a script that "shouldn't need it"** — a formatter that suddenly starts calling `fopen` is at best a bug, at worst a supply-chain compromise of a dependency the script pulled in.

### Frontend/template path — untrusted-author threat surface, mitigated by capability restriction

The "template author" role is **untrusted** — whoever the outer app allows to POST to `/api/preview` or `/api/drafts`. In a foreUP-style outer app this would be authenticated operators; in a public-demo deployment it could be anyone with browser access. The sandbox must assume the script author is hostile.

**But** the capability grant is small enough to bound the blast radius even under successful sandbox escape:

- Script can read only what the report already renders → **no data exfiltration beyond what the request was already going to expose.** The user viewing the report at `/api/reports/foo` was already going to see those rows; a hostile script reading them and encoding them into the output achieves nothing beyond what the normal render does.
- No network → **no data exfiltration to a third-party endpoint.** Even if the script observes something interesting (a sensitive row value), there is no way to transmit it off the server. It can only be embedded in the rendered report — which is already visible to the request originator.
- No filesystem → **no lateral read into other tenants' data, other reports' cached files, or system files.**
- No arbitrary data-source access → **no cross-report data reach.** A script attached to report X cannot query report Y's data source, even if both data sources are registered in the same PHP process.

The remaining hostile-author threats are:

- **CPU DoS** — a script that infinite-loops or does expensive computation on every row of a 500-row report can consume server CPU. Bounded time per invocation + rate-limiting on the preview endpoint mitigate.
- **Memory DoS** — a script that allocates unboundedly can consume server memory. Bounded memory per invocation.
- **Output-size DoS** — a script that returns a multi-megabyte string can consume bandwidth. Bounded output size.
- **Preview-endpoint abuse** — the debounced live-preview loop lets a hostile author trigger many script executions rapidly. Rate-limit at the endpoint.

These are DoS-shaped threats, not exfiltration threats. They matter but are qualitatively easier to handle than "attacker can read arbitrary files."

### Attacker capabilities the sandbox MUST prevent (both paths)

The following applies to the *secondary path* absolutely, and to the *primary path* as defense-in-depth against script bugs and dependency compromise:


- **Arbitrary code execution outside the sandbox** — no `system`, `exec`, `shell_exec`, backticks, `passthru`, `popen`, `proc_open`. In JavaScript: no `Function(...)` constructor, no `eval`, no dynamic `import`.
- **Filesystem access** — no `fopen`, `file_get_contents`, `file_put_contents`, `require`, `include`, `include_once`, `require_once`. In JavaScript: no `fetch('file://...')`, no `require('fs')`, no `import('...')`.
- **Network access** — no `curl_*`, `fsockopen`, `stream_socket_client`, `Http::get()`, no `fetch()`.
- **PHP/global mutation** — no `ini_set`, `putenv`, `session_*`, `header`, no ability to modify $GLOBALS or `Env`.
- **Reflection escapes** — no `ReflectionClass::newInstanceArgs`, no `Closure::bind`, no ability to walk from any accessible object back to a raw callable.
- **Denial of service** — bounded CPU time per invocation (single-digit milliseconds), bounded memory (single-digit MB), no infinite loops (deterministic instruction-count limit or wall-clock timeout).
- **Information leaks** — no access to `$_SERVER`, `$_ENV`, no ability to introspect PHP-loaded modules or extensions, no error messages that leak filesystem paths.

Attacker capabilities the sandbox MAY allow:

- **Read** the current row data, the aggregate rows for the band's scope, and the `$params` passed to `fill()`. This is the entire point of the extension — the script needs the reporting context.
- **Read** the current time (bounded; not `microtime(true)` for timing side-channels — a coarsened tick counter is safer).
- **Return** a scalar value (string, number, bool, or null) that becomes the cell content or a modified band.
- **Log** structured messages to a controlled sink (useful for template debugging; sink is capped in size and rate).

The bar: an attacker who can author templates can consume server resources on their own templates' render cycles, but cannot exfiltrate data outside the reporting context, cannot pivot to other tenants' data, cannot persist code, and cannot cross the process boundary.

---

## Design directions (ranked by likely-fit)

**How to read the directions.** These five design candidates apply *per `ScriptRuntime` implementation*, not as mutually exclusive choices for the platform overall:

- **v1 `PhpScriptRuntime` (in scope for v1):** primarily Direction 2 (whitelisted PHP AST subset); Direction 4 (subprocess) and Direction 5 (WASM) are secondary options.
- **Future `JsScriptRuntime`:** primarily Direction 3 (QuickJS in PHP) or Direction 5 (WASM); Direction 4 (subprocess to Node) is a secondary option.
- **Direction 1 (expression language)** stands apart — it's a *different requirement shape* (no user scripting; declarative expressions instead). Retained as a fallback if the sandbox cost turns out to exceed value.

Directions 2 and 3 map naturally to specific runtime implementations. Directions 4 and 5 (subprocess, WASM) are runtime-implementation strategies that could apply to either language.

### Direction 1 — Custom expression language (safest, most restricted)

A small domain-specific language that compiles to a value at fill time. Similar shape to Symfony ExpressionLanguage, JMESPath, or JSONata. The user "script" is not PHP or TypeScript — it's a purpose-built expression grammar with no I/O, no function calls beyond an allowlist, no loops beyond bounded comprehensions.

**Trade-off:** doesn't satisfy the "PHP or TypeScript" part of the requirement — different language shape entirely. But it does satisfy the *capability* the user is asking for (declarative logic in templates) at a fraction of the security cost.

If the requirement is strictly "PHP OR TypeScript source text goes in, values come out," skip. If the requirement is really "users can express logic in templates," this is the highest-safety option.

### Direction 2 — Whitelisted PHP AST subset (medium safety, medium effort)

Parse the user's PHP with `nikic/PHP-Parser`, walk the AST, reject any node type not on an allowlist. Whitelist ~15 node types: literal expressions, arithmetic, comparisons, array access, subset of native string/number functions (`strtolower`, `abs`, `round`, etc.), local variable assignment, `if`/`else`, `return`. Reject: function definitions, class use, `new`, `include`, `eval`, function calls not on the allowlist, `use` statements, magic constants, `$this`, superglobals.

After AST validation, evaluate via a small tree-walking interpreter (not `eval`) that never emits actual PHP bytecode. Every AST node type has an interpret function. Loops are bounded (max iterations enforced at interpret time). Wall-clock timeout via a signal/tick handler.

**Trade-off:** substantial implementation work (a whole interpreter), and every PHP language addition requires an audit. But it satisfies "PHP scripting" literally, is auditable, and has no shared state with the host process. There's a body of prior art (`nikic/PHP-Parser` is stable; `symfony/expression-language` is a good reference for the interpreter pattern).

### Direction 3 — QuickJS-in-PHP for TypeScript (medium safety, low-medium effort)

Compile TypeScript in the browser at save time using `esbuild-wasm`. Store the precompiled JavaScript in the JSON template alongside the source. At render time, execute the JavaScript in a QuickJS sandbox running inside PHP (via a PHP FFI wrapper for the QuickJS library, or via a small sidecar process).

QuickJS is a compact JavaScript engine designed for embedding — no `fetch`, no `require`, no DOM, no Node globals. Every capability injected into the sandbox is explicit (you hand it an object shaped like `{ row: {...}, params: {...}, aggregateRows: [...] }`). The evaluator has built-in instruction-count and memory limits.

**Trade-off:** ships a native dependency (QuickJS), and PHP FFI is not universally available in production PHP builds (needs `--with-ffi` at compile time or the extension bundled). The TypeScript side satisfies the requirement genuinely; PHP still needs a separate sandbox story (Direction 1 or 2).

### Direction 4 — Deno sidecar process per invocation (highest isolation, highest cost)

Spawn a `deno eval --deny-all --allow-read=/dev/null 'script text'` subprocess for each script execution. Pipe the row context in via stdin as JSON; read the return value from stdout. Deno's permission model is opt-in and airtight; a script with no `--allow-*` flags cannot read the filesystem, network, environment, or hrtime.

**Trade-off:** process spawn per invocation is expensive (100–200ms overhead). Not viable for computed expressions that fire per-row on a 500-row report (would add 50–100 seconds). Viable for band-level hooks that fire O(bands) times, not O(rows) — and since the primary path (backend-authored) targets hooks, formatters, and data sources rather than per-row computed content, Direction 4 is actually a strong contender for the primary path. A better variant: long-lived sidecar process pooled across invocations, communicating over a Unix socket — amortizes the process-spawn cost. Cost per call drops to ~5 ms.

The pooled-sidecar variant is a plausible primary-path candidate: strongest isolation available, no PHP FFI or QuickJS extension required, TypeScript support is native.

### Direction 5 — WebAssembly runtime (Wasmtime, Wasmer)

Compile PHP or TypeScript to WASM ahead of time, execute in a WASI-restricted runtime. Emerging story for PHP (PHP-on-WASM is an active project); mature for TypeScript via `esbuild` + a WASI polyfill. Same shape as Direction 3 but broader applicability.

**Trade-off:** stack still maturing for PHP. If we're building this in 2027+, this direction becomes plausible; today (2026-08-22) it's earlier than ideal for production dependence.

---

## Constraints on any chosen design

Independent of which direction wins, the sandboxed environment MUST provide:

- **Determinism.** Same script + same context = same output. No PRNG, no `microtime`, no network, nothing that could vary between two calls. This preserves the pipeline's deterministic-output invariant, which is what makes snapshot tests possible.
- **Bounded time.** Hard wall-clock cap per invocation (proposed default: 50 ms for computed expressions, 200 ms for band hooks). Exceeded → script is aborted, band/element renders as an error placeholder ("[script timeout]"), and the failure is logged with the script source (redacted if sensitive) and the input context.
- **Bounded memory.** Proposed cap: 4 MB working memory per invocation. Exceeded → same abort behavior.
- **Bounded output size.** A returned string longer than N characters (proposed: 8 KB) is truncated with a warning. Prevents a script from constructing multi-megabyte cell values.
- **Structured error surface.** A script that throws inside the sandbox does not crash the render. The band/element renders an error placeholder with a stable shape so the layout math doesn't destabilize.
- **No shared state between invocations.** Every invocation starts with a fresh sandbox context. A script cannot cache state across calls (this is a feature — it makes ordering-dependent bugs impossible).
- **No access to other bands' data during evaluation.** A script attached to band X sees X's row and aggregate context, not other bands'. Same reasoning as the Fill/Layout boundary — narrow the surface, contain the blast radius.

---

## Preview-endpoint considerations

Preview (`POST /api/preview`) is where template-authored scripts execute during editing — the live-preview loop of the builder posts the current JSON on every debounced keystroke and re-renders.

Both paths are relevant at the preview endpoint:

- **Backend-registered scripts** (already loaded on boot) run as part of the preview render just like they run in production. No per-preview loading, no additional risk. Preview errors that trace to a backend script surface with the script's file path so the developer can find it.
- **Template-authored scripts** embedded in the previewed JSON run under the restricted-context sandbox. Preview must:
  - Run scripts in the same sandbox that production rendering would use (so preview behavior matches).
  - Surface script errors in the builder UI's error banner (not as generic 500s).
  - Rate-limit script execution across the endpoint (e.g. max N script invocations per preview call, max M preview calls per session per minute) to prevent DoS through the debounced live-preview loop.
  - Log script executions with the template draft ID + user identifier for audit / rate-limit accounting.
  - Enforce bounded time / memory / output-size per invocation (same as production render).

Preview is the primary place hostile template scripts would be exercised, so its rate-limiting and quota accounting matter more than any other endpoint's.

---

## Migration path from today

### Backend path — additive to existing seams, imported explicitly per-report

Backend-authored scripts slot in as importable modules, resolved per-render:

- A `ScriptResolver` interprets import specifiers (`@builtin/…`, `@vendor/…`, `./relative/…`) into concrete script files. Base directory for `./relative/…` is configurable per host app (e.g. `writer-app/scripts/`); package-scoped specifiers resolve through composer / npm autoloading.
- **No auto-discovery, no boot-time registration.** Nothing loads until a report imports it.
- When a report is filled, the runtime reads its `imports` block, resolves each specifier, loads each referenced script into a **render-scoped sandbox** (fresh per fill call), and exposes the returned callable under the local alias declared in the imports block.
- The alias is scoped to that render. Two reports rendering simultaneously with the same script imported under different aliases are isolated.
- The existing PHP-code path for `FormatterRegistry::defaults()`, `DataSourceRegistry`, and `DefinitionFiller::onBand()` stays. Shipped/code-registered strategies remain implicit vocabulary. Scripts are an *additional*, per-render, opt-in extension surface.
- Errors during import resolution (specifier doesn't resolve, script fails to load, sandbox limit exceeded at load time) are template-level errors: the render fails with a diagnostic naming the failing import and the reason. Adjacent reports that don't import the failing script are unaffected.
- In development, a filesystem watcher can invalidate resolved-script caches on change. In production, a resolved script is cached per host-app-process for the lifetime of the process (fresh sandbox per render, cached compiled/parsed form of the script).

### Frontend/template path — restricted-context capability grant

Additive to the backend path. Frontend scripts run in a JavaScript engine (browser at page render; Node during Vite dev) — a different host runtime from the v1 `PhpScriptRuntime` on the backend, but a shared sandbox *design*. The core invariants — no I/O primitives, no arbitrary function construction, structured errors, no shared state across invocations — apply identically. What the frontend restricts additionally is the capability grant (read-only reporting context; no data source *registration*, only *access*). **Templates use two script sources:**

- **Inline scripts** embedded in the template body — anonymous, one-off, source lives in the template JSON:
  - New `computed` content type: `{ "type": "computed", "lang": "php" | "ts", "source": "..." }`
  - New `scripts` array on band definitions: `{ "id": "detail", "type": "detail", "elements": [...], "scripts": [{ "phase": "post-build", "lang": "ts", "source": "..." }] }`
  - Formatters can also be defined inline for one-off value transformations.
- **Imported scripts** from the same `imports:` mechanism the backend path uses — templates can name a script from the pool that the backend has made available for this report, and use its alias in the same places (`"format": "usd"`, `"call": "hide_zero"`, `"data_source": "sales_by_category"`). The gate is at the "backend has allow-listed this script for this report" level, not at the script's authoring type.
- **Script-against-invocation-results** — a template inline script can reference the results of any backend-invoked script the report has access to. Example: an inline `computed` script can call `context.dataSource('sales_by_category').rows()` to iterate rows that the report's data source already fetched, or invoke a backend-authored formatter (`context.formatters.usd(value)`) to reuse it inside a larger transformation.

**How does this stay safe if a template can invoke a backend script that internally reads a database?**

The invocation surface a template gets for a backend-authored script is what the *script's contract* returns — the already-computed rows for a data source, the formatted string for a formatter, the transformed band for a hook. The template cannot re-parameterize the underlying I/O. `context.dataSource('sales_by_category').rows()` returns *the rows this report already fetched with its own params*, not `.query(sql)` or `.fetch(differentParams)`. The template's read is bounded by what the report was already going to load.

The scripts backend attaches to a report define the report's data envelope. Template scripting operates inside that envelope. The envelope is set by backend policy (which imports a report is allowed to declare, which data sources are permitted); template authors work within it, they don't get to widen it.

The frontend sandbox always denies direct I/O regardless of what the invoked backend scripts internally do. A template calling `context.dataSource('x').rows()` is fine — it's an accessor over already-fetched data. A template trying to `fetch('https://...')` fails — the global doesn't exist in the sandbox. That two-layer model (accessors permitted, primitives denied) is the invariant.
- The sandbox environment for template-authored scripts injects a `context` object exposing:
  - `context.row` — the current row (in `detail` bands)
  - `context.aggregateRows` — rows in the band's scope (in `group-footer` / `summary` bands)
  - `context.params` — the params passed to `fill()`
  - `context.groupValue` — the current group key (in `group-header` / `group-footer` bands)
  - `context.dataSource(name)` — read-only accessor for data sources registered for this report; returns `.rows()` (the already-fetched rows) or throws if `name` isn't registered for this report
  - Safe stdlib bindings (math functions, `String`/`Number`/`Date` for TS; `strtolower`/`abs`/`round`/etc. for PHP)
- The sandbox environment explicitly does NOT inject: `fetch`, `XMLHttpRequest`, `require`, `import`, `curl_*`, `fopen`, `file_*`, `Http::*`, or any other I/O primitive. Attempting to reference them fails with `ReferenceError` (JS) or `Error` (PHP).
- The security-scanner agent's Rule R5 gets a new subclause: *"ComputedExpression built from data is 🔴 unless it flows through the restricted-context sandboxed runtime with no network/filesystem/cross-report-data access; the restricted sandbox path is explicitly allowed."*

---

## What this document is NOT

- **Not a decision.** No direction is chosen. When implementation gets scheduled, someone runs a proper brainstorming session over these five directions (or new ones that have emerged), picks one, writes an ADR under `docs/09-conventions/decisions/`, and *that* ADR is the decision.
- **Not a scope commitment for Sub-project A or B.** Sub-project A ships without user scripting. Sub-project B (the structured form builder) may or may not include it — decision deferred to when B's brainstorming happens.
- **Not a design spec.** The output shape (JSON schema for `computed`, sandbox API surface, error format) will be nailed down when a direction is chosen.

---

## Cross-references

- [`fill-to-layout-schema.md`](fill-to-layout-schema.md) — the fill/layout contract this feature must not violate (script output goes into `TextContent`, layout math untouched)
- [`layout-algorithm-spec.md`](layout-algorithm-spec.md) — determinism requirement (§ 4.6, § 19) — any sandbox choice must preserve identical-input-identical-output
- `.claude/agents/security-scanner.md` § Rule R5 — the current forbid-callable-from-data rule that this feature revises
- `writer/src/Fill/DefinitionFiller.php::resolveElement()` — the switch statement that would grow a `case 'computed':` branch
- `writer/src/Fill/DefinitionFiller.php::onBand()` — the hook seam that would accept sandboxed scripts alongside PHP callables
- `writer/src/Expression/ComputedExpression.php` — the class that would gain a sandbox-runtime construction path
