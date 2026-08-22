# ADR-009: Rename library from `foreup/reporting` to `edgecase123/report-writer`

**Status:** Accepted
**Date:** 2026-08-22
**Related:** [Ticket 010](../../tickets/010-library-rename-to-edgecase.md)

## Context

The library is currently packaged as `foreup/reporting` with PSR-4 namespace `foreup\Reporting\`. The project was extracted from a larger foreUP application. Because the standalone build is being made in a way that lets the owner ship it independently of foreUP's decisions, the whole codebase needs to be foreUP-agnostic.

Three neutralization levels were considered:

- **Full rename** — library + wrapper + docs all move to a neutral name/namespace
- **Neutral wrapper only** — library stays `foreup/reporting`; new wrapper is neutral, references the library as an installed dep
- **Defer** — build the wrapper neutral, decide library rename later

## Decision

**Full rename.** Package `foreup/reporting` → `edgecase123/report-writer`; namespace `foreup\Reporting\` → `ReportWriter\`. All foreUP references stripped from library README, wrapper docs, and every `use` statement.

## Rationale

- Ownership of the code is with the owner (edgecase123), so the rename is legitimate
- If the outer foreUP app later adopts this project, they change one line of `use` statements — a mechanical find-replace on their side. Small cost, one-time
- If the outer foreUP app does not adopt this project, the owner can ship it under their own name to a third-party market. That path is not viable if the library is called `foreup/reporting`
- The alternative (leaving `foreup/reporting` visible in code) means anyone reading the source encounters foreUP branding on a project that isn't foreUP's product. Confusing at best, blocking at worst

## Rejected alternatives

- **Neutral wrapper only.** Half-measure — the library is still foreUP-branded internally, so any developer opening `writer/src/` sees a name that doesn't match the outer packaging. Fails the third-party-market use case
- **Defer.** The rename touches ~30 files mechanically; deferring means every subsequent change lands under the wrong name and has to be re-renamed later. Cheaper to do it once, at the start of the standalone build

## Consequences

- Package name: `edgecase123/report-writer`
- PSR-4 root: `ReportWriter\` → `writer/src/`
- Test namespace: `ReportWriter\Tests\` → `writer/tests/`
- `writer/README.md` stripped of foreUP references, examples updated to new namespace
- ~30-file mechanical rename tracked as [Ticket 010](../../tickets/010-library-rename-to-edgecase.md); should land in the same PR as [Ticket 002 (dead code deletion)](../../tickets/002-delete-dead-code-definition-namespace.md) so the renamed library ships lean
