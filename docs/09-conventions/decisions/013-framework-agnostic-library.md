# ADR-013: Library is framework-agnostic; `writer-app/` + Docker + npm are dev/test/demo scaffolding only

**Status:** Accepted
**Date:** 2026-08-22
**Related:** [ADR-001](001-slim-4-http-layer.md), [ADR-003](003-docker-compose-ports-and-containers.md), [ADR-009](009-library-rename-to-edgecase.md)

## Context

Mid-design it became clear that the library must be usable in Symfony 5.x/7 **and** Laravel 10+ **and** plain PHP — the production home is whichever framework the consumer picks. Slim was chosen as the HTTP layer for the demo (ADR-001) and Docker Compose was chosen as the local orchestration (ADR-003), and these were at risk of drifting into "the recommended production stack." They are not.

## Decision

**The library `writer/` (published as `edgecase123/report-writer`) is framework-agnostic PHP.** It ships as a composer package with no PSR-7 request/response dependency, no PSR-11 container dependency, no framework namespaces, no assumptions about HTTP dispatch, DB access, session handling, or DI.

**Everything else in the repo — `writer-app/` (the Slim 4 host), `docker/`, `docker-compose.yml`, `frontend/package.json`'s build wiring, npm scripts — is developer scaffolding for the following purposes only:**

- Local development iteration
- Automated testing (both library unit tests and end-to-end demo smoke)
- Reference implementation showing "here's ONE way to wire this up"
- Demo/screencast material for handoff conversations

**None of this scaffolding is prescriptive for consumers.** A consumer adopting the library brings their own framework, DI container, HTTP layer, database access, and orchestration. The demo's Slim controllers are throwaway from the consumer's perspective; the demo's SQLite providers are throwaway; the demo's Docker Compose file is throwaway.

## Rationale

- **Consumer freedom.** Locking the library to Slim, or to Symfony, or to Laravel, forecloses adoption by projects that use anything else. Framework-neutrality is a market-size decision.
- **Portability of tests.** Library unit tests (Layer 1) and snapshot tests (Layer 3) are already framework-neutral by construction — the assertions are about `filler → ReportInstance → LayoutService → renderer → string`, and no HTTP or framework code sits inside that pipeline. Only Slim smoke tests (Layer 2, 4 tests) are throwaway; that's an acceptable ratio.
- **Existing shape is already correct.** The library currently uses only PHP-native interfaces (`ReportFillerInterface`, `ReportDataSourceInterface`, `RendererInterface` — all one-method POPO interfaces). Registries are plain classes with no PSR-11 dependency. Fillers accept `array $params`. Renderers return strings. No code changes are needed on the library side to enforce this decision; it's already framework-neutral. This ADR captures that as a durable rule so future contributions don't inadvertently couple.
- **Handoff clarity.** Adoption docs need to be explicit about which parts are the library (portable, adopt) and which parts are scaffolding (reference only). This ADR makes that distinction the load-bearing framing.

## Rules that follow from this decision

- **No Slim/Symfony/Laravel type hints in `writer/src/**`.** Grep for `use Slim\`, `use Symfony\Component\HttpFoundation\`, `use Illuminate\` under `writer/src/` must return zero results forever.
- **No PSR-7 request/response objects flowing into or out of the library.** Fillers take arrays; controllers (whichever framework) unpack from their own request objects and pack into their own response objects.
- **No PSR-11 container required by the library.** Registries and factories are enough. If a consumer's DI is PSR-11, they wire it up themselves at composition-root.
- **No environment-variable reads inside the library.** Configuration flows through constructor arguments from the consumer's composition-root.
- **Any adapter for a specific framework** — Symfony HttpFoundation shim, Laravel service provider, Slim controller helpers — lives in a **separate optional composer package** (or in the consumer's own codebase), never in `writer/src/`.

## Rejected alternatives

- **Slim as the recommended production framework.** Rejected — Slim is a fine microframework for demos but not a real production target for reporting-in-a-larger-app. Consumers using Symfony/Laravel would have to strip Slim scaffolding anyway.
- **Ship framework-specific adapters** (`edgecase123/report-writer-symfony`, `edgecase123/report-writer-laravel`) alongside the core library. Attractive but deferred — each adapter is another package to maintain. Better to let consumers write their own thin wrappers (documented in `docs/handoff/adoption.md`) and only extract into shared packages if a real pattern emerges across multiple adopters.
- **Ship no demo at all** (library-only repo). Would keep the boundary crisp but loses the "run this in 60 seconds and see what it does" value. The demo is real work; it just isn't a production template.

## Consequences

- **`docs/handoff/adoption.md`** becomes the load-bearing handoff artifact. It covers three consumer scenarios (Symfony 5.x/7, Laravel 10+, plain PHP), each showing the wiring pattern. Written after implementation lands, per [ADR-011](011-docs-after-implementation.md).
- **Slim smoke test suite kept minimal** — 4 tests, proving "the demo boots." Not a template for consumer test suites.
- **Snapshot tests** are the portable value on the `writer-app/` side — they validate the library's rendering guarantees using SQLite as a convenient data source but with no Slim in the loop; port to any framework by swapping composition-root wiring.
- **Any future contributor** who wants to add a "helpful" adapter for their favorite framework routes it through a new discussion and probably lands it as a separate package, not in `writer/src/`.
- **CI (if added later)** runs against multiple PHP versions but does not need to run against multiple frameworks — the library has no framework touchpoints to break.
