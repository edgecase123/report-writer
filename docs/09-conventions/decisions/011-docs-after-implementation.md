# ADR-011: Documentation follows implementation, not ahead of it

**Status:** Accepted
**Date:** 2026-08-22

## Context

Mid-brainstorming, developer documentation was being written ahead of implementation — planned pages for code that didn't exist yet (the standalone runtime, the builder UI, the Docker setup). The user pushed back with a rule: **docs after implementation, always unless otherwise specified**.

## Decision

**Developer documentation follows the code.** Design work does not simultaneously produce doc pages for planned code.

- **During design:** focus on design output (specs, plans, decisions). Write ADRs for load-bearing choices; write specs in `docs/architecture/`. Do not create pages under `01-overview/`, `04-pipeline/`, `05-authoring/`, `06-runtime/`, etc. for code that doesn't exist yet.
- **After code lands:** THEN write the corresponding developer page (or update an existing page's `covers:` list), same PR when possible.
- **Doc pages describing already-shipped behavior** (the existing library, upstream architecture specs mirrored from another repo) are fine — they document real artifacts.
- The `docs/README.md` index may list *(planned)* pages as a roadmap — that's a directory sign, not a doc. Actual page files should not exist until the code they describe does.
- **Explicit override:** if the user says "document this as we design," follow that instruction.

## Rationale

Speculative docs:

- Go stale before they're read (design changes mid-implementation)
- Misrepresent the code because they describe what was planned, not what was built
- Force rework when the shape drifts

Real docs describe real code. The lag isn't a bug; it's the entire point.

The exception carved out for explicit override is deliberate: the user explicitly asked for the user-scripting doc to be written ahead of implementation because the requirement itself needs to be captured now to avoid getting lost. That's a legitimate override — but a specific one, per artifact.

## Rejected alternatives

- **Docs during design.** Rejected — this is the failure mode the rule exists to prevent
- **Docs never** (only code + comments). Rejected — some ongoing developer documentation is genuinely valuable; the rule times the writing, not the existence

## Consequences

- The `docs/README.md` index shows planned pages as roadmap entries; those files don't exist until the corresponding code lands
- After implementing a feature, updating the docs is part of the same PR — same rule Leagues applies via the `covers:` frontmatter block
- Saved as a persistent feedback memory at `~/.claude/projects/-Users-leejenkins-dev-report-writer/memory/feedback_docs_after_implementation.md` so future Claude sessions apply it without being reminded
