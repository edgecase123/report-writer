# Architectural Decision Records (ADRs)

Load-bearing decisions about the shape of the project. Each ADR captures **what** we decided, **why** we decided it, and **what we rejected** in the process. Format modeled on standard ADR practice — one file per decision, numbered sequentially, immutable once accepted (superseded by a new ADR if reversed).

## How to add one

1. Copy the shape of an existing ADR (e.g. [001](001-slim-4-http-layer.md)).
2. Number sequentially (`00N-kebab-title.md`).
3. Set status = `Accepted` when written; `Superseded by ADR-NNN` if a later decision reverses it.
4. Add an entry to the ledger below in the same PR.

## Ledger

| ADR | Title | Status | Date |
|---|---|---|---|
| [001](001-slim-4-http-layer.md) | Slim 4 as the standalone runtime's HTTP layer | Accepted | 2026-08-22 |
| [002](002-sqlite-coffee-shop-toy-domain.md) | SQLite + coffee-shop toy domain for the standalone runtime | Accepted | 2026-08-22 |
| [003](003-docker-compose-ports-and-containers.md) | Docker Compose orchestration, non-conflicting ports, namespaced container names | Accepted | 2026-08-22 |
| [004](004-hash-based-mini-router.md) | Hash-based mini-router instead of vue-router | Accepted | 2026-08-22 |
| [005](005-json-editor-split-screen-live-preview.md) | Split-screen JSON editor with debounced live preview + CodeMirror 6 | Accepted | 2026-08-22 |
| [006](006-autocomplete-levels-a-b-in-subproject-a.md) | Autocomplete Levels A+B (static enums + dynamic names) in Sub-project A | Accepted | 2026-08-22 |
| [007](007-imports-over-directory-discovery-for-scripts.md) | Imports over directory-discovery for user scripts | Accepted | 2026-08-22 |
| [008](008-frontend-scripting-restricted-context.md) | Frontend scripting in scope with restricted-context capability grant | Accepted | 2026-08-22 |
| [009](009-library-rename-to-edgecase.md) | Rename library from `foreup/reporting` to `edgecase123/report-writer` | Accepted | 2026-08-22 |
| [010](010-sub-project-decomposition-a-then-b.md) | Sub-project decomposition: A (runtime + JSON builder) → B (structured form builder later) | Accepted | 2026-08-22 |
| [011](011-docs-after-implementation.md) | Documentation follows implementation, not ahead of it | Accepted | 2026-08-22 |
| [012](012-github-issues-optional-local-tickets-first.md) | Local ticket files first, GitHub issues on promotion | Accepted | 2026-08-22 |
| [013](013-framework-agnostic-library.md) | Library is framework-agnostic; `writer-app/`+Docker+npm are dev/test/demo scaffolding only | Accepted | 2026-08-22 |
