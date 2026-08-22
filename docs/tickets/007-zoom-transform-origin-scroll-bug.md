# TICKET-007: Fix zoom transform-origin causing unreachable overflow at >100%

**Priority:** Medium
**Source:** frontend-designer audit (2026-08-22) — 🔴 Design
**Scope:** `frontend/src/components/ReportCanvas.vue`

## Problem

At zoom levels >100%, the scaled report grows wider than the viewport. The canvas uses `align-items: center` on a flex column, which triggers the well-known flexbox centered-overflow bug: the scaled element's overflowing left edge becomes unreachable because scroll only goes right.

Current values:

- `transform-origin: 'top center'` (in `ReportCanvas.vue:16`)
- `align-items: center` (in scoped `.viewer-canvas`)

Trade-off: switching to `transform-origin: top left` + `align-items: flex-start` fixes the scroll but visually left-aligns the report at ≤100% zoom, which is a regression for the common case.

## Proposed fix

Reserved-width wrapper approach:

- Keep `align-items: center` and `transform-origin: top left`
- Wrap the scaled `.report-inner` in a `.report-scaler` div whose width tracks `basePageWidth * zoomLevel`
- The wrapper takes the horizontal space, so the scroll container knows the true content width; the scaled report itself sits inside at its natural (unscaled) width

Rough shape:

```vue
<div class="report-scaler" :style="{ width: `${basePageWidth * zoomLevel}pt`, height: `${basePageHeight * zoomLevel}pt` }">
  <main class="report-inner" :style="{ transform: `scale(${zoomLevel})`, transformOrigin: 'top left' }">
    ...
  </main>
</div>
```

`basePageWidth` / `basePageHeight` come from the page config the server used (612 × 792 pt for US Letter default). May need to expose via a data attribute on the report HTML or hardcode with a config override.

## Acceptance criteria

- [ ] At zoom levels 50% through 200%, the full report is horizontally scrollable in both directions
- [ ] At ≤100% zoom, report remains visually centered in the canvas
- [ ] Print output unaffected (still 1:1)
- [ ] Manual test at 50%, 100%, 150%, 200% on a wider-than-viewport report

## Notes

- Was applied and then reverted in the 2026-08-22 fix pass because the naive fix (just switch to `top left` + `flex-start`) caused a visual regression at ≤100%. Needs the wrapper approach or an equivalent.
- Base page dimensions might come from a `data-page-width`/`data-page-height` on the injected report HTML if the server emits it. Currently the server doesn't. Consider adding.
