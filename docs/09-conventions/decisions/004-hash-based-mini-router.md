# ADR-004: Hash-based mini-router instead of vue-router

**Status:** Accepted
**Date:** 2026-08-22

## Context

Sub-project A grows the Vue frontend from one surface (the viewer) to three (Home landing, Viewer, Builder). This requires routing.

## Decision

Ship a ~20-line hash-based mini-router in `frontend/src/router.ts`. Do not add vue-router.

## Rationale

- Three routes total. vue-router's public API is bigger than the routing we need.
- Hash routing (`#/viewer`, `#/builder`) requires zero server-side URL-rewriting configuration — works with any static file server, any embed context, even `file://`
- The mini-router is essentially: reactive ref of `location.hash`, `hashchange` listener updating it, computed component picker. Under 20 lines.
- Removing a runtime dependency simplifies the built bundle and keeps the frontend surface legible in one sitting
- The frontend is already deliberately spartan (no Pinia, no vue-router, no icon library, no design-token file) — adding vue-router breaks that discipline for negligible gain

## Rejected alternatives

- **vue-router with history mode.** Requires server-side rewrites so `/builder` doesn't 404 on refresh; adds ~15 KB gzipped; brings its own API concepts (`RouterLink`, `useRouter`, guards, navigation resolution) that dwarf the routing logic we actually need
- **vue-router with hash mode.** Same dependency cost as history mode; the mini-router covers hash mode's use cases in a fraction of the code
- **Sub-domain routing** (`viewer.localhost` / `builder.localhost`). Requires local DNS setup; deployment complexity spikes

## Consequences

- URLs have `#` in them. Fine for a demo/testbed; if this ever becomes a customer-facing product where `/viewer` reads better than `/#/viewer`, this decision gets reversed
- The router is thin enough that if it grows past ~50 lines or adds guards/lazy-loading needs, revisit the decision. That's a signal to move to vue-router, not to extend the mini-router indefinitely
- Sub-project B's structured form builder (a fourth route) can slot in without changing this decision
