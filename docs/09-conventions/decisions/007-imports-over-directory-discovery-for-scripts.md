# ADR-007: Imports over directory-discovery for user scripts

**Status:** Accepted
**Date:** 2026-08-22
**Related:** [`docs/architecture/user-scripting.md`](../../architecture/user-scripting.md)

## Context

The user-scripting future direction ([Ticket 015](../../tickets/015-implement-user-scripting.md)) needs a mechanism for backend-authored scripts to be discovered and made available to reports. Two shapes were considered:

- **Directory-scan discovery** — drop files in `writer-app/scripts/{hooks,formatters,data-sources}/`, boot-time walker registers everything found globally
- **Explicit imports** — scripts sit in the filesystem but nothing loads unless a report names them in an `imports:` block, analogous to `import`/`require`/`use` in general-purpose languages

## Decision

**Explicit imports.** Backend scripts live in the filesystem, but they only load when a report references them via an `imports:` block. No auto-registration on boot.

Import specifier grammar (final syntax TBD):

- `@builtin/…` — shipped-with-library primitives
- `@vendor/package/…` — from installed composer/npm packages
- `./relative/…` — from a configured scripts base directory, validated against traversal
- Bare names not supported — always explicit

Local aliases (LHS of the imports map) are per-report scoped.

## Rationale

- **Isolation.** A bug in a script affects only reports that imported it — not every report in the system
- **Discoverability.** Reading a report's template tells you exactly which scripts it depends on. No hidden "boot registered a global hook for band 'detail'"
- **Testability.** A test of one report loads only the scripts that report imports. No system-wide side effects
- **Reuse without pollution.** Same script can be imported by many reports; adding a new script to the pool doesn't affect existing reports
- **Determinism.** Two identical templates render identically regardless of what other scripts exist in the pool. Auto-discovery couples the render to filesystem state at boot, breaking the pipeline's deterministic-output invariant
- **Versioning surface.** Once imports are explicit, they can carry version constraints later (`"usd": "@builtin/formatters/currency@^2"`). Auto-discovery has nowhere to put version info

## Rejected alternatives

- **Directory-scan discovery.** Rejected for the reasons above. Implicit globals become a debugging nightmare at any nontrivial scale
- **Auto-discovery with opt-out** ("scripts register, but a report can `exclude: [...]` to skip some"). Inverts the ergonomics — you have to know what exists to opt out. Fails the discoverability argument
- **Namespace autoloading only** (composer-style, no explicit imports at the report level). Would work for backend code paths but the template surface can't reference a class by FQN — it needs an alias

## Consequences

- The shipped vocabulary (`FormatterRegistry::defaults()`, aggregate functions) stays implicit — those are the pipeline's *primitives*, not scripts. Anything that IS a script requires an import
- New `ScriptResolver` (interprets import specifiers into files) and `ScriptRegistry` (loaded-scripts cache) become part of the runtime
- Registry-per-render (fresh sandbox per fill call, cached parse/compile per host process lifetime) — not global state
- Template errors (import doesn't resolve, script fails to load, sandbox limit exceeded at load time) surface with diagnostics naming the failing import
