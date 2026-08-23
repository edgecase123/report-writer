# TICKET-019: Viewer should re-center the report when zoom changes

**Status:** Open
**Priority:** Medium (UX — surfaced during manual test of PR #14 / Ticket 007)
**Source:** manual test 2026-08-23 — reserved-width scaler wrapper landed in PR #14 fixes scroll reachability, but the report does not visually re-center when the user zooms in or out
**Scope:** `frontend/src/components/ReportCanvas.vue`, possibly `frontend/src/state/viewerState.ts`

## Problem

After [PR #14](https://github.com/edgecase123/report-writer/pull/14) landed the reserved-width scaler wrapper (Ticket 007), the horizontal-scroll unreachability bug is fixed. But zoom-driven UX still feels wrong: when the user changes zoom level via `+` / `−` / `Reset` / preset select, the report's position within the viewport does not re-center around the previous focal point.

Concrete symptoms observed:

- At 150% zoom, scroll to the right edge. Click `−` (back to 125%). The scroll position is preserved in raw px, so the report jumps to a different visual anchor rather than staying centered on the previously-visible area.
- At 100% zoom, click `+` a couple of times. The report grows outward from `top-left` (per the new `transform-origin`), so the visible content shifts unpredictably — the user's mental "current view" is lost.

The user's expectation (a standard document-viewer convention): after any zoom change, the viewport should re-anchor around the previously-visible center point, OR at minimum the report should be visually re-centered horizontally in the canvas.

## Design options

**Option A — Re-center on every zoom change (simple).**

After `zoomLevel` changes, imperatively reset `scrollLeft` / `scrollTop` on `.viewer-canvas` so the scaler is horizontally centered and vertically at top. The user always sees the top-center of the scaled report post-zoom.

- Simplest to implement (one `watch(zoomLevel, ...)` on scroll reset).
- Loses focal-point preservation — if the user was reading page 3 and zooms, they jump back to page 1.
- Matches the ticket's minimal ask: "both zooming in and out should center the report."

**Option B — Preserve focal point (nicer UX).**

Before the zoom transform applies, capture the current center-of-viewport in report coordinates. After the transform, adjust `scrollLeft`/`scrollTop` so that same coordinate lands at the center again.

- Matches the behavior of Preview / Acrobat / Chrome PDF viewer.
- More work — needs `beforeUpdate` / `nextTick` hooks, coordinate math against the scaler's transformed origin.
- Only worth the complexity if users routinely zoom while reading long reports.

**Option C — Change `transform-origin` back to `top center` and rely on flexbox.**

Would undo PR #14's fix and reintroduce the unreachable-overflow bug. Not viable — listed for completeness only.

## Recommendation

Start with **Option A**. It closes the immediate UX complaint from the manual test, is a one-line implementation, and doesn't foreclose Option B if we later want the focal-point-preserving behavior. Ship it as a small follow-up to PR #14.

## Acceptance criteria

- [ ] After any zoom change (via `+`, `−`, `Reset`, or preset select), the `.viewer-canvas` scroll position resets so the scaler is visually centered horizontally.
- [ ] Vertical scroll position may reset to top-of-page or be preserved — pick one and document.
- [ ] Manual test: at 100% zoom scrolled to the right edge, click `+` → report re-centers horizontally at 125%. Click `−` twice → same behavior back to 100% and 75%.
- [ ] Print output unaffected.

## Notes

- The scaler wrapper approach from Ticket 007 means the scroll container's true content width matches `basePageWidth * zoomLevel`. Setting `scrollLeft = (scrollWidth - clientWidth) / 2` centers it horizontally regardless of zoom level.
- Vue's `watch(zoomLevel, ...)` fires on any change source (buttons, select, external `zoomTo`). Wire the reset there.
- If Option B is chosen later, this ticket can be closed and a new ticket filed for the focal-point-preserving logic.
