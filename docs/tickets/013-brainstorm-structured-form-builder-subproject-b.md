# TICKET-013: Brainstorm structured form builder (Sub-project B)

**Priority:** Epic
**Source:** session-design (2026-08-22) — sub-project split
**Status:** Blocked on Sub-project A landing
**Scope:** New Vue frontend surface (probably a `/#structured-builder` route or a new mode); backend endpoint additions if needed

## Problem

Sub-project A ships a raw JSON editor with autocomplete as its builder UI. That's the mid-tier option. The README-mentioned goal is a "structured form builder" — add/remove/reorder bands and elements through forms, with JSON generated under the hood. That's a substantial UX surface of its own.

## What Sub-project B adds

- Form-driven band composition (drag/drop or add/reorder controls; per-band type picker; per-element position/size/content editor)
- Data-source picker with field auto-completion from `/api/data-sources`
- Formatter picker
- Live preview integrated with the same `/api/preview` endpoint from Sub-project A
- Draft save/load via the same `/api/drafts` endpoints
- Formal JSON Schema for the template format (Level C autocomplete becomes the underlying validation) — this is shared infrastructure with the form builder

## When to start

**After Sub-project A ships.** The runtime, the preview endpoint, and the JSON template format all need to be real and stable before designing on top of them. Doing B in parallel with A means:

- API shape decisions in A get locked in before B has a chance to inform them
- The form-builder UX gets designed against imagined endpoints instead of real ones
- Both projects churn each other

## Deliverable

Own brainstorming session (invoke `superpowers:brainstorming`) once A is running. That session produces its own design + implementation plan.

## Notes

- Also depends on [Ticket 015 (user scripting)](015-implement-user-scripting.md) being at least partially designed — because the form builder needs UI for attaching scripts (import panel, inline script editors), which requires knowing the scripting model
- Consider whether B ships in the same repo or as a plugin/extension
