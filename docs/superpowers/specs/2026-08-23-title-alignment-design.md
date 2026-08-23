# Title Alignment — decouple title band from columns

**Status:** Approved design, ready for implementation plan
**Date:** 2026-08-23
**Ticket:** [017 — Title Alignment vs Columns vs Page](../../tickets/017-title-alignment-vs-columns-vs-page.md)
**Scope:** `writer/src/Builder/ReportBuilder.php`, `writer/src/Layout/LayoutService.php`, `writer/src/Instance/ElementInstance.php`, `writer-app/src/Reports/DailySalesFiller.php`, associated tests

## Problem

`ReportBuilder::title()` at `writer/src/Builder/ReportBuilder.php:168-172` builds the title band by reading widths from *another band's content*:

```php
$totalWidth = $this->totalWidth();   // walks $this->columns
$titleBand  = new BandInstance('band_title', 'title', [
    new ElementInstance('title', 0.0, 0.0, $totalWidth, $this->titleHeight, ...),
]);
```

The title element's width is derived from the columns' extent. `StyleMap`'s default CSS applies `text-align: center` to `.fu-band-title`, so text centers *within the title element's box*. When columns don't span the printable page width — the common case — the title's box is narrower than the page, and the visual result appears left-of-page-center.

The visible symptom (off-center title) is a leading indicator of a deeper architectural smell: **one band is derived from another band's contents**. That coupling is always wrong; it just happens to *look* right when columns extent equals printable area.

## Design principle

The pipeline is Fill → Layout → Stream → Render. Under that pipeline:

- **Fill** authors against an abstract staging area. It speaks in absolutes *relative to that canvas* — column widths, band heights it has chosen. It does not know about physical pages, print margins, or paper size.
- **Layout** is where abstract becomes physical. `LayoutService` owns `PageConfig` and is the sole stage that knows the real page geometry.
- **Renderer** consumes concrete positioned elements and draws. Pure. No math.

Two invariants follow:

1. **A band's elements must not derive from another band's elements or contents.** Each band sizes its own elements from its own state, or defers sizing downstream.
2. **The abstract-vs-physical distinction is resolved at the Fill→Layout boundary.** Fill may declare "no opinion on width" and Layout supplies the concrete value from `PageConfig`. Downstream stages (Stream, Render) see only concrete values — the "unspecified" concept dies at Layout.

## Design

### Fill (`ReportBuilder`)

- Delete the `$totalWidth = $this->totalWidth();` line and the `$totalWidth` argument from the title element's constructor call.
- Emit the title element with `width = 0.0` (the "no declared width" sentinel).
- `totalWidth()` itself stays; group-header bands (line 339, 347) still call it. Applying the same principle to group headers is a follow-up decision, deliberately out of scope here (a group-header banner that spans its columns is arguably intentional intent, not a coupling smell).

### Sentinel semantics (`ElementInstance::width`)

- Type stays `float` (non-nullable).
- **`0.0` means "no declared width; layout substitutes the staging area's printable width."**
- A literal zero-width element has no rendering meaning, so the sentinel is unambiguous in practice.
- Documented in a one-line docblock on `ElementInstance::__construct`.

### Layout (`LayoutService`)

- Add a private helper on `LayoutService` that resolves an element's effective width against `PageConfig`:

  ```php
  private function resolvedWidth(ElementInstance $el): float
  {
      return $el->getWidth() ?: $this->pageConfig->printableWidth();
  }
  ```

- Call `resolvedWidth($el)` from every site that emits a `PositionedElement` — currently `placeBand()` (once) and `splitAndPlace()` (twice, for the first-page chunk and the continuation chunk).
- Pagination decisions (`bandHeight`, `fits`, `isSplittable`) are all height-based today and do not read `width`, so ordering within `layout()` is not sensitive. The single load-bearing rule: **every `PositionedElement` that leaves Layout has a concrete positive width.** The sentinel never crosses the Layout→Stream boundary.

### Renderer

- **No changes.** `HtmlRenderer` and `JsonRenderer` continue to consume concrete widths on every `PositionedElement`. The sentinel never reaches them.

### DefinitionFiller

- **No code changes.** DefinitionFiller's static bands emit elements from explicit JSON template fields (`x`, `y`, `width`, `height`). There is no implicit `totalWidth()`-style derivation, so the bug does not exist on this path.
- JSON template authors gain the capability for free: `"width": 0` on any element definition triggers the same Layout-side substitution.

## Test plan

Unit — `writer/tests/Unit/Builder/`:
- New assertion (or new test) that `ReportBuilder::create(...)->title('X')->build()`'s single title element has `getWidth() === 0.0`. Regression guard against re-introducing the coupling.

Unit — `writer/tests/Unit/Layout/LayoutServiceTest.php`:
- New test: an element built with `width = 0.0` emerges from `layout()` as a `PositionedElement` whose `getWidth() === pageConfig->printableWidth()`.
- New test: a non-zero-width element passes through unchanged (regression guard on the substitution logic — it must fire only for the sentinel).
- Existing tests continue to pass unchanged.

Integration / snapshot — location TBD by the implementation plan:
- A `ReportBuilder`-authored report with columns narrower than printable area (e.g. three 120pt columns on default Letter) renders a title `PositionedElement` with `width === 572.0` and `x === 20.0` (marginLeft). This is the regression test against the original visual bug.

Demo — `writer-app`:
- Revert `writer-app/src/Reports/DailySalesFiller.php`'s Option-D workaround (columns widened to sum to 572pt) back to natural per-column widths. Update any associated snapshot / assertion. Verifies the fix lands end-to-end at the demo level.

## Docs

- Close [Ticket 017](../../tickets/017-title-alignment-vs-columns-vs-page.md): mark accepted, link this spec.
- Add a short paragraph on the `width = 0.0` sentinel to `docs/architecture/` — either as a section in an existing architecture doc or a new focused doc. This is the durable note that other elements (starting with group-header, if that ever moves) may adopt the same convention. One or two paragraphs; not a normative spec.

## Non-goals

- **Group-header decoupling.** `totalWidth()` still drives group-header widths. Whether to apply the same principle there is a separate ticket.
- **Adding page-config awareness to `ReportBuilder`.** The builder remains PageConfig-free.
- **New fluent methods on `ReportBuilder`.** No `->titleWidth()`, no `->titleAlignment()`. The whole feature is achieved by *removing* the coupling; no new API surface.
- **JSON template schema changes.** DefinitionFiller's template format is unchanged; the new capability is expressed via existing `width` field taking the value `0`.

## Migration and compatibility

- **Existing `ReportBuilder` consumers**: any report whose columns spanned the printable area (e.g. sum to 572pt on default Letter) is byte-identical after the fix. Reports whose columns were narrower will see the title expand to printable width — this is the intended fix, and matches what those consumers presumably wanted.
- **Existing `DefinitionFiller` consumers**: zero change. JSON templates that specify concrete widths continue to render identically.
- **External consumers of the library**: none in the wild (edgecase123/report-writer is at 0.1.0, single repo).
