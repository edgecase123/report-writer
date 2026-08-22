# ADR-012: Local ticket files first, GitHub issues on promotion

**Status:** Accepted
**Date:** 2026-08-22

## Context

Tickets from the 2026-08-22 audit pass needed a destination. Options: create GitHub issues directly on `edgecase123/report-writer`, or write local files under `docs/tickets/` that can be promoted later.

## Decision

**Local ticket files** at `docs/tickets/` as the default, shaped GitHub-issue-compatible so they can be promoted via `gh issue create --body-file <ticket.md>` when appropriate.

Individual file per ticket (`NNN-kebab-title.md`), numbered sequentially. Index at `docs/tickets/README.md` with a ledger + bulk-promotion script snippet.

## Rationale

- Local files are safer as a default — creating GitHub issues is a durable external action visible to anyone with repo access. Deferring the GitHub commit keeps options open
- Local files iterate cheaply — a mid-flight decision to reword, split, or drop a ticket is a git commit, not a GitHub-issue edit
- Format is identical to what `gh issue create --body-file` accepts, so promotion is one bash loop, not a rewrite
- Reading tickets requires only the local checkout — no network round-trip for someone reviewing scope

## Rejected alternatives

- **GitHub issues directly.** Would work but locks in the "these are issues on the repo" decision immediately. Some of the audit findings might turn out to be wrong or non-issues on second look; easier to iterate on local files
- **Tickets in the same repo as code, tracked in Git only.** That's what this decision is. The rejection here would have been "don't track them at all," which is unacceptable — the audit findings need to persist

## Consequences

- All Sub-project A followups and audit findings live in `docs/tickets/*.md`
- Promoting to GitHub is a scripted bulk operation when the owner decides it's time. Script snippet in `docs/tickets/README.md`
- If a ticket is promoted to GitHub, the local file stays and gains an `Upstream: edgecase123/report-writer#N` line for traceability
- New tickets going forward: create local first (numbered sequentially, updating the index in the same commit); promote to GitHub only when the owner is ready for the ticket to be public/shared
