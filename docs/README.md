# Developer Documentation

> Living reference for developers working on report-writer.
> If something is wrong or stale, fix it here — these docs are part of the codebase.

## What this is

End-to-end documentation for a deterministic reporting pipeline: turn tabular row data into pixel-honest paginated reports that render identically on screen and paper. Ships as two components — a pure PHP library (`edgecase123/report-writer`) and a Vite + Vue 3 viewer/builder — plus a Slim 4 host app that wires them together against a SQLite database of sample data.

**New here and don't know how reporting works?** Read [Concepts → What is a report?](03-concepts/what-is-a-report.md) first. Reporting has its own vocabulary (bands, elements, expressions, layout) that this system uses precisely. Once those click, the code reads itself.

**Coming from a Laravel or Rails background?** Skim [Overview → Architecture](01-overview/architecture.md) to see what's the same and what's different (spoiler: no ORM, no framework in the library, no request lifecycle in the pipeline).

**Just want to run it?** [Setup → Quickstart](02-setup/quickstart.md).

**Working on the layout engine?** Read [Architecture Specifications](architecture/) — the v1 target spec for the layout stage, mirrored from the upstream repo. The current library implements a proper subset of that spec; the gap between them is the ongoing v1 work.

---

## Reading order for a new developer

1. [Overview → Product](01-overview/product.md) — what report-writer does
2. [Overview → Architecture](01-overview/architecture.md) — the two components + the pipeline stages
3. [Concepts → What is a report?](03-concepts/what-is-a-report.md) — reporting fundamentals (assumes zero prior knowledge)
4. [Concepts → Bands, elements, and content](03-concepts/bands-elements-and-content.md) — the core primitives
5. [Setup → Quickstart](02-setup/quickstart.md) — running locally
6. [Authoring → Overview](05-authoring/overview.md) — the three ways to write a report
7. [Pipeline → Overview](04-pipeline/overview.md) — how a report becomes rendered output

After that, drill into whichever section matches your next task.

---

## Contents

### [01 — Overview](01-overview/)
| File | What it covers |
|---|---|
| [product.md](01-overview/product.md) | What report-writer is, who uses it, what problem it solves |
| [architecture.md](01-overview/architecture.md) | Tech stack, high-level system diagram, the two components |
| [glossary.md](01-overview/glossary.md) | Every term used in the system, precisely defined |

### [02 — Setup](02-setup/)
| File | What it covers |
|---|---|
| [quickstart.md](02-setup/quickstart.md) | *(planned)* Clone → run → view a report in under 10 minutes |
| [docker.md](02-setup/docker.md) | *(planned)* Docker Compose services, ports, HMR, common startup issues |
| [environment.md](02-setup/environment.md) | *(planned)* Every environment variable, what it controls, safe defaults |

### [03 — Concepts](03-concepts/)
Reporting fundamentals — no prior reporting knowledge assumed.

| File | What it covers |
|---|---|
| [what-is-a-report.md](03-concepts/what-is-a-report.md) | The tabular metaphor, why pagination matters, why points-not-pixels |
| [bands-elements-and-content.md](03-concepts/bands-elements-and-content.md) | *(planned)* The core primitives explained from first principles |
| [reports-vs-templates.md](03-concepts/reports-vs-templates.md) | *(planned)* Hand-coded reports (PHP) vs data-driven reports (JSON) |
| [expressions-and-formatters.md](03-concepts/expressions-and-formatters.md) | *(planned)* How cell content is computed and displayed |

### [04 — Pipeline](04-pipeline/)
The four-stage pipeline in detail. One page per stage.

| File | What it covers |
|---|---|
| [overview.md](04-pipeline/overview.md) | *(planned)* The four stages and the invariant that connects them |
| [01-fill.md](04-pipeline/01-fill.md) | *(planned)* Turning params into a `ReportInstance` |
| [02-layout.md](04-pipeline/02-layout.md) | *(planned)* Turning bands into positioned elements on pages |
| [03-stream.md](04-pipeline/03-stream.md) | *(planned)* The handoff format between layout and render |
| [04-render.md](04-pipeline/04-render.md) | *(planned)* HTML vs JSON output, escaping, `StyleMap` |

### [05 — Authoring reports](05-authoring/)
Three ways to write a report. Which one to reach for.

| File | What it covers |
|---|---|
| [overview.md](05-authoring/overview.md) | *(planned)* The three fill paths and when to pick each |
| [report-builder.md](05-authoring/report-builder.md) | *(planned)* Writing a report in PHP with the fluent builder |
| [json-template.md](05-authoring/json-template.md) | *(planned)* Writing a report as a JSON definition |
| [custom-filler.md](05-authoring/custom-filler.md) | *(planned)* When the shipped paths don't fit — implementing `ReportFillerInterface` directly |
| [grouping.md](05-authoring/grouping.md) | *(planned)* Single-level and nested grouping, per-group aggregates |
| [subreports.md](05-authoring/subreports.md) | *(planned)* Composing one report inside another |

### [06 — Runtime host app](06-runtime/)
The Slim 4 app that serves reports over HTTP.

| File | What it covers |
|---|---|
| [overview.md](06-runtime/overview.md) | *(planned)* What the host app does; scope vs the library |
| [routes.md](06-runtime/routes.md) | *(planned)* Every HTTP route and its contract |
| [data-sources.md](06-runtime/data-sources.md) | *(planned)* The SQLite-backed data providers; how to add one |
| [container.md](06-runtime/container.md) | *(planned)* Dependency wiring |
| [database.md](06-runtime/database.md) | *(planned)* The coffee-shop schema, seed strategy, migrations |

### [07 — Frontend](07-frontend/)
The Vite + Vue 3 viewer and builder.

| File | What it covers |
|---|---|
| [overview.md](07-frontend/overview.md) | *(planned)* Architecture, routes, state modules |
| [viewer.md](07-frontend/viewer.md) | *(planned)* How the report canvas fetches and renders HTML |
| [builder.md](07-frontend/builder.md) | *(planned)* Split-screen JSON editor + live preview |
| [state.md](07-frontend/state.md) | *(planned)* The `state/` module pattern |

### [08 — Testing](08-testing/)
| File | What it covers |
|---|---|
| [overview.md](08-testing/overview.md) | *(planned)* Philosophy, layers (unit / integration / snapshot), running the suite |
| [writing-a-report-test.md](08-testing/writing-a-report-test.md) | *(planned)* Fixture rows → filler → `ReportInstance` assertions |

### [09 — Conventions](09-conventions/)
| File | What it covers |
|---|---|
| [code-style.md](09-conventions/code-style.md) | *(planned)* PSR-12, immutability rules, naming |
| [decisions/README.md](09-conventions/decisions/README.md) | Architectural Decision Records — ledger + 12 ADRs from the 2026-08-22 standalone-build session |

### [Tickets](tickets/)
Local ticket store — GitHub-issue-shaped, promotable via `gh issue create --body-file` to `edgecase123/report-writer` when appropriate. Index at [`tickets/README.md`](tickets/README.md).

### [Architecture Specifications](architecture/)
Normative v1 target specs and future-direction documents. **Not the current implementation** — describes where the pipeline is heading.

| File | What it covers |
|---|---|
| [README.md](architecture/README.md) | Index + status ledger of what's shipped, what's partial, what's not started |
| [fill-to-layout-schema.md](architecture/fill-to-layout-schema.md) | Fill/Layout contract, subreport container model, band splitting rules, minimal pseudocode |
| [layout-algorithm-spec.md](architecture/layout-algorithm-spec.md) | RFC-style normative spec (MUST/SHOULD) for the v1 layout engine |
| [test-cases-future.md](architecture/test-cases-future.md) | Two canonical test cases asserting the v1 output shape |
| [user-scripting.md](architecture/user-scripting.md) | Hard future requirement — PHP/TypeScript scripting in band hooks and `computed` content, threat model, five design directions |

---

## Keeping these docs current

Each page has a `covers:` frontmatter block listing the source files it describes. When you change those source files, update the doc too — same PR. Same rule for adding a source file: add it under `covers:` on whichever page introduces it.

Use [`_template.md`](_template.md) as the starting point for any new page.

The index above is the canonical list of what exists. If a file doesn't appear here (or shouldn't), fix the index in the same PR that adds or removes the file.

Pages marked *(planned)* are stubs or missing entirely, waiting for their subject-matter code to land. As sub-projects progress, planned pages fill in.
