# ADR-001: Slim 4 as the standalone runtime's HTTP layer

**Status:** Accepted
**Date:** 2026-08-22
**Deciders:** lee (owner), Claude (facilitator)

## Context

Sub-project A needs an HTTP layer to expose the pipeline as `/api/reports/{id}`, `/api/preview`, `/api/drafts` etc. Options considered: bare PHP + hand-rolled router, Slim 4, Symfony HttpFoundation only, full Symfony skeleton.

## Decision

Use **Slim 4** as the microframework.

## Rationale

- Small, well-understood, PHP 7.4 compatible
- Route handlers translate almost 1:1 to Symfony controllers — if the outer foreUP app ever adopts this project, porting back is minimal work
- Adds one composer dependency
- The whole app remains legible in one sitting
- No autowiring surface required — a ~60-line hand-rolled PSR-11 container suffices

## Rejected alternatives

- **Bare PHP + hand-rolled router.** Zero deps but the controller shape drifts furthest from what the outer foreUP app would want; porting back becomes rewrite instead of translation.
- **Symfony HttpFoundation only.** Closer shape to the outer app but requires more setup ceremony than Slim to reach the same functionality.
- **Full Symfony skeleton.** Overkill for a testbed. Would spend more time on framework wiring than on reports.

## Consequences

- Slim 4 becomes a runtime dependency of `writer-app/`
- The Slim controller pattern (constructor-inject dependencies, one action method per route) becomes the reference for anyone extending the runtime
- If a future decision moves back to full Symfony, the controllers translate mechanically
