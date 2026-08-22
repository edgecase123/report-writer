# ADR-006: Autocomplete Levels A+B (static enums + dynamic names) in Sub-project A

**Status:** Accepted
**Date:** 2026-08-22

## Context

The split-screen JSON editor from [ADR-005](005-json-editor-split-screen-live-preview.md) supports autocomplete via `@codemirror/autocomplete`. Three levels of sophistication were considered:

- **Level A** — Static enum completions (band types, content types, aggregate functions, top-level keys, alignment values)
- **Level B** — Dynamic name completions from the server (formatter names, data source names, field names within a data source)
- **Level C** — Full JSON Schema with `codemirror-json-schema` package: validation + completions + hover tooltips generated from a formal schema

## Decision

**Levels A + B in Sub-project A.** Level C deferred to Sub-project B where the schema becomes shared infrastructure with the structured form builder.

## Rationale

**A + B in A:**

- Static enums are cheap — one `completions.ts` module (~150 lines), no schema authoring
- Dynamic name completions turn the editor from "type it and hope" into "explore what's available"
- Backend endpoints already expose the metadata needed (`GET /api/data-sources` returns `rowSchema`, `GET /api/formatters` returns names) — no backend changes
- Fits inside Section 4's scope without expanding surface

**Level C deferred:**

- Authoring a formal JSON Schema is ~1 day of real schema work — real, but a real project of its own
- The structured form builder (Sub-project B) is itself a schema-driven UI — the schema becomes shared infrastructure between the form builder and the editor's autocomplete
- Deferring gives real-user feedback on which fields confuse people, so the schema's `description` fields (which drive hover tooltips) get written from evidence rather than guessed
- Landing Level C in A would front-load design work with no B to justify it

## Rejected alternatives

- **Level A only.** Missing the dynamic name completions removes most of the discoverability benefit — knowing that band types exist doesn't help if you can't discover which data sources are available
- **Level C in Sub-project A.** Doubles the frontend design effort in A for a benefit that mostly compounds in B

## Consequences

- `@codemirror/autocomplete` becomes a runtime frontend dependency
- New files: `frontend/src/editor/completions.ts`, `frontend/src/editor/snippets.ts`, `frontend/src/state/schemaState.ts`
- Schema-state cache: fetch `/api/data-sources` and `/api/formatters` once per session, cache in module refs. No cache invalidation — a developer who adds a data source refreshes the tab
- Snippet expansions (`detail⇥`, `band⇥`, `column⇥`) come essentially for free once autocomplete infrastructure is in place
- Simple synchronous linter flags unknown enum values before Preview submission
