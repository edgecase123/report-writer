# TICKET-011: Zoom preset dropdown — decide on placeholder behavior

**Priority:** Low
**Source:** frontend-designer audit (2026-08-22) — 🟡 Design
**Scope:** `frontend/src/components/ViewerToolbar.vue`, `frontend/src/state/viewerState.ts`

## Problem

The zoom preset `<select>` shows a placeholder option (`{{ Math.round(zoomLevel * 100) }}%`) when the current zoom doesn't match a preset. This option is `disabled` — the user sees the odd percentage but can't re-select it.

After the recent zoom-step alignment (0.10 → 0.25), stepping from 100% now lands on 125% (a preset) instead of 110% (not), so the placeholder appears less often. But it still appears if the user zooms via the browser's own zoom or if we ever change presets.

## Proposed fix

Two options:

**Option A — Numeric input** (recommended):

Replace the `<select>` with an `<input type="number" min="50" max="200" step="5">` bound to the percentage. Users can type any value; presets become quick-access buttons if desired.

**Option B — Snap-to-nearest-preset**:

On `+` / `-` clicks, snap to the next / previous preset instead of adding/subtracting a fixed step. Presets stay the source of truth for zoom levels; free-typing isn't supported.

## Acceptance criteria

- [ ] Decision documented (A or B)
- [ ] Placeholder-with-odd-percent state no longer appears in normal flow
- [ ] Zoom controls remain keyboard-accessible

## Notes

- Low priority — cosmetic after the step-alignment fix already applied.
- If we ship the split-screen builder UI (Sub-project A), the zoom control might live in a different context and this decision could be revisited then.
