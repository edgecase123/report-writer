# ADR-010: Sub-project decomposition: A (runtime + JSON builder) → B (structured form builder later)

**Status:** Accepted
**Date:** 2026-08-22

## Context

The standalone-build ask covered a wide surface: HTTP runtime, sample database and reports, Docker orchestration, viewer wiring, and a builder UI. The builder UI option-space alone spanned "JSON editor + preview button" (small) through "structured form builder" (large, matching the README's planned Vue-based builder).

Attempting to design and build all of it in one implementation pass would produce a design doc that's really two designs stapled together, and an implementation plan too large to execute cleanly.

## Decision

Split into two sub-projects:

**Sub-project A — Standalone runtime + JSON split-screen editor**
- Docker Compose orchestration
- Slim 4 host app
- SQLite coffee-shop schema + seed + sample reports
- Viewer wired end-to-end
- Home landing + Builder page with split-screen JSON editor (with Levels A+B autocomplete)
- Handoff docs

**Sub-project B — Structured form builder UI (later)**
- New Vue surface for composing JSON templates via forms
- Add/remove/reorder bands and elements through forms
- Depends on A's `/api/data-sources`, `/api/preview`, template persistence
- Its own design fork on save/load, live-preview shape, band/element form ergonomics
- Full JSON Schema (autocomplete Level C) becomes shared infrastructure with B

A must land first because B depends on A's endpoints and data model. B decisions (how templates persist, what the preview API looks like, what data-source enumeration returns) are only well-informed *after* A exists in code.

## Rationale

- Each sub-project gets its own brainstorming session, its own design doc, its own implementation plan, its own PR set
- A is scoped tightly enough to complete in a single implementation push
- B's design is materially better after A is running because B can reference real endpoint behavior instead of imagined behavior
- The user's revised choice (mid-brainstorm) was "A should include the JSON split-screen builder; the structured form builder is B" — this ADR captures that split

## Rejected alternatives

- **One combined spec.** Would produce a spec too long to review and an implementation plan too large to execute; likely to need mid-flight re-scoping
- **Brainstorm B's shape now in parallel with A.** Sounds nice but forces A to design around imagined B constraints; better to let A shape the endpoints B will consume

## Consequences

- Sub-project A's brainstorming was completed (mostly) in this session; Sections 5–6 still pending
- Sub-project B waits until A ships to run its own brainstorming session
- Tickets [012](../../tickets/012-implement-standalone-runtime-subproject-a.md) and [013](../../tickets/013-brainstorm-structured-form-builder-subproject-b.md) track the two sub-projects
