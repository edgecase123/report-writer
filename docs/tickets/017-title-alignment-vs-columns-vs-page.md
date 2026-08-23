# TICKET-017: Title element width — columns extent vs printable page width

**Status:** ✅ Closed — resolved via `width = 0.0` sentinel + Layout substitution
**Priority:** Low (cosmetic; visible in demo reports where columns don't span printable area)
**Source:** design discussion 2026-08-23 — noticed while running the A2 Daily Sales demo
**Scope:** `writer/src/Builder/ReportBuilder.php` (title band construction), possibly `writer/src/Layout/PageConfig.php`, possibly `writer/src/Renderer/StyleMap.php`

## Problem

`ReportBuilder::title($text, $height)` at `writer/src/Builder/ReportBuilder.php:87-91` constructs the title band as:

```php
$totalWidth = $this->totalWidth();
$bands[] = new BandInstance('band_title', 'title', [
    new ElementInstance('title', 0.0, 0.0, $totalWidth, $this->titleHeight, new TextContent($this->titleText)),
]);
```

`totalWidth()` returns `max(column.x + column.width)` across all columns. The library's default StyleMap (`writer/src/Renderer/StyleMap.php:38-43`) applies `text-align: center` to `.fu-band-title`.

Consequence: the title centers within the columns-extent box, not the printable-page-width area. On reports where columns are narrower than the printable area (e.g. three 120pt columns on a Letter page with 20pt margins → columns span 380pt, printable area is ~572pt), the title visually appears left-of-page-center by ~96pt.

Visible today in `writer-app/src/Reports/DailySalesFiller.php`'s Daily Sales report (columns totalled 380pt, printable width ~572pt) before the workaround landing alongside this ticket.

## Design options

**Option A — Change default to page-width.** Modify `ReportBuilder::title()` to use `pageConfig.width - marginLeft - marginRight` instead of `totalWidth()`. Breaks every existing consumer's title positioning. Requires passing `PageConfig` into the builder somehow (currently the builder doesn't know about layout). Also note: `PageConfig` presently has no `marginRight` field — only `marginLeft`, `marginTop`, `marginBottom` — so this option would also require adding one (a small library-shape change on its own).

**Option B — Opt-in flag.** Add `->titleAlignment('columns' | 'page')` or `->titleSpan('columns' | 'printable-area')` fluent method. Default preserves current behavior; opt-in gets page-centered title. No breaking change. Requires the builder to hold a PageConfig reference (or defer the width decision to layout time).

**Option C — Move responsibility to the renderer.** Have the HtmlRenderer detect title bands and re-center them based on the page's printable-area width at render time. Bypasses the builder's need to know about PageConfig. Cleanest separation but leaks title-specific logic into the renderer.

**Option D — Do nothing.** Consumers who want a page-centered title widen their last column so `totalWidth()` matches the printable-area width. Currently the accepted workaround (see the DailySalesFiller demo fix landing alongside this ticket).

## Recommendation

Not yet decided. Option B feels least invasive but adds API surface for a cosmetic-only feature. Option D is what the demo does today. Revisit when a real consumer complains, or when A3's five additional reports make the ordering-and-widening workarounds tedious.

## Related

- Discussion: 2026-08-23 session on A2 Daily Sales demo rendering
- Demo workaround landed with this ticket: `writer-app/src/Reports/DailySalesFiller.php` columns widened so `totalWidth()` ≈ printable area (572pt on Letter with 20pt margins). Not a fix — just moves the visual anchor.

## Resolution

Decoupled the title band from column widths: `ReportBuilder::title()` emits the
title element with `width = 0.0` (the "no declared width" sentinel);
`LayoutService` substitutes `PageConfig::printableWidth()` at `PositionedElement`
emission. Fill stays PageConfig-free; renderer unchanged.

- Design spec: [`docs/superpowers/specs/2026-08-23-title-alignment-design.md`](../superpowers/specs/2026-08-23-title-alignment-design.md)
- Implementation plan: [`docs/superpowers/plans/2026-08-23-title-alignment-implementation.md`](../superpowers/plans/2026-08-23-title-alignment-implementation.md)
- Architecture note: [`docs/architecture/element-width-sentinel.md`](../architecture/element-width-sentinel.md)

Group-header bands still derive their element width from `totalWidth()`; whether
to apply the same principle there is a separate ticket (worth its own decision,
since a group-header banner "spanning the columns it groups" is arguably
intentional intent, not a coupling smell).
