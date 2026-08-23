# TICKET-020: Viewer needs drag-to-pan with hand-grab cursor when zoomed

**Status:** Open
**Priority:** Medium (UX — surfaced during manual test of PR #14 / Ticket 007)
**Source:** manual test 2026-08-23 — reserved-width scaler wrapper landed in PR #14 makes the report scrollable at high zoom, but there is no non-scrollbar way to move it
**Scope:** `frontend/src/components/ReportCanvas.vue`, possibly `frontend/src/state/viewerState.ts`

## Problem

After [PR #14](https://github.com/edgecase123/report-writer/pull/14) landed the reserved-width scaler wrapper (Ticket 007), users can scroll horizontally and vertically when the scaled report exceeds viewport size. But the only way to move the report is via the scrollbars — no drag-to-pan interaction.

Standard document viewers (Preview, Adobe Acrobat, Figma, browser PDF viewer, image viewers) all support the same "hand tool" metaphor: hover over the content shows a grab cursor; mousedown-drag pans the viewport; release drops the position.

Missing on the current viewer:

- No `cursor: grab` when hovering over the report at any zoom level (or specifically when zoomed > viewport size).
- No mousedown-drag interaction to translate the scroll position.
- No visual affordance that the report is pannable at high zoom.

## Design shape

**Interaction:**

- When the report content is larger than the viewport in EITHER axis (i.e. `.viewer-canvas` has horizontal or vertical overflow), set `cursor: grab` on the scroll container.
- On `mousedown`, capture the initial mouse position + initial scroll position. Set `cursor: grabbing`.
- On `mousemove` while dragging, update `scrollLeft` / `scrollTop` by the mouse delta.
- On `mouseup` / `mouseleave`, release drag; restore `cursor: grab` (or `default` if content fits).

**Modifier consideration:**

- Some viewers make hand-pan the default (Acrobat with Hand Tool selected).
- Some require spacebar-hold to activate (Photoshop, Figma).
- Reports are read, not edited — default-on hand cursor is fine. No mode toggle needed.

**Interaction with text selection:**

- Text elements in reports are selectable (default browser behavior on the injected HTML). Drag-to-pan would collide with drag-to-select.
- Options:
  - (a) Hand-pan only when mousedown originates on non-text (background, page margin). Text selection still works if mousedown is on a text element.
  - (b) Hand-pan always; text selection disabled on the report content (`user-select: none` on `.report-inner`).
  - (c) Spacebar-hold gate — hand-pan only when spacebar is held, otherwise text selection is normal.
- Preference: (a) — most natural. Users click on empty background to pan; click on text to select. Matches PDF viewer conventions.

**Touch / mobile:**

- Out of scope for now. Viewer is desktop-only per project design conventions (US Letter print orientation).
- Touch pan via `touch-action: pan-x pan-y` works natively via scrollbars; drag-to-pan would need pointer-event handling. Defer.

## Acceptance criteria

- [ ] When the scroll container has horizontal or vertical overflow, the cursor over the report is `grab`.
- [ ] Mousedown-drag on the report (originating on non-text) translates the scroll position by the drag delta.
- [ ] Cursor changes to `grabbing` during active drag; back to `grab` on release.
- [ ] Text selection on report elements still works when the mousedown originates on a text element.
- [ ] Print output unaffected (cursor is a screen-only concern; no `@media print` change needed).
- [ ] Manual test: at 200% zoom on a wide report, hover the background → grab cursor; drag left/right/up/down → report pans; drag on a text element → text selection (no pan).

## Notes

- The scaler-wrapper approach from Ticket 007 means the scroll container is `.viewer-canvas` and its `scrollLeft`/`scrollTop` are the levers to move. Drag delta translates directly to scroll delta.
- Consider using a `useDragPan()` composable if this pattern ever appears elsewhere (unlikely in a report viewer, but the split-screen builder UI from Sub-project A might want it on the JSON preview).
- Related follow-up: [Ticket 019](019-viewer-zoom-recentering.md) — different UX concern (zoom-driven re-centering), same file.
