# TICKET-018: PageConfig missing right margin and printableWidth()

**Status:** ✅ Closed (2026-08-23) — implementation shipped in the same commit
**Priority:** Medium (unblocks [Ticket 017](017-title-alignment-vs-columns-vs-page.md) Options A + C, and closes an API asymmetry in PageConfig)
**Source:** design discussion 2026-08-23 — surfaced while exploring Ticket 017 title-alignment options
**Scope:** `writer/src/Layout/PageConfig.php`, `writer/src/Layout/LayoutService.php` (one-line caller update from the rename below), `writer/tests/Unit/Layout/PageConfigTest.php` (new)

## Problem

`PageConfig` currently defines three margin constants — `DEFAULT_MARGIN_TOP`, `DEFAULT_MARGIN_BOTTOM`, `DEFAULT_MARGIN_LEFT` — plus corresponding fields, constructor params, and getters. **No `DEFAULT_MARGIN_RIGHT` exists**, and there is no field, constructor param, getter, or `printableWidth()` method for the horizontal analog of the existing `printableHeight()`.

Consequence: the vertical axis is honest (`printableHeight() = height - marginTop - marginBottom` at `writer/src/Layout/PageConfig.php:43`, used by `LayoutService` to detect page overflow) but the horizontal axis is asymmetric. Any code that wants "printable page width" today has to compute `pageConfig.getWidth() - pageConfig.getMarginLeft()`, which:

- Assumes zero right margin — silently different from the visual convention (all four sides usually have margins)
- Blocks [Ticket 017](017-title-alignment-vs-columns-vs-page.md) Option A (title centers on page width) and Option C (renderer re-centers on printable area) — neither can be implemented without a computable printable-width value from PageConfig

`LayoutService` today does NOT enforce a right-side bound — elements can overflow past `width - marginLeft` and layout silently allows it. Whether to add right-side overflow detection is a separate design question and NOT in scope for this ticket.

## Proposed fix

Additive changes to `writer/src/Layout/PageConfig.php`, plus one small rename (see item 6 below) that touches `LayoutService`. Non-breaking for external consumers of the 0.1.0 library because (a) constructor additions have default values, and (b) the renamed method has no external consumers — only one internal caller in `LayoutService`, updated in the same commit.

1. Add constant:
   ```php
   public const DEFAULT_MARGIN_RIGHT = 20.0;
   ```

2. Add field:
   ```php
   private float $marginRight;
   ```

3. Add constructor parameter (last positional, default value → non-breaking for existing `new PageConfig(...)` calls):
   ```php
   public function __construct(
       float $width        = self::DEFAULT_WIDTH,
       float $height       = self::DEFAULT_HEIGHT,
       float $marginTop    = self::DEFAULT_MARGIN_TOP,
       float $marginBottom = self::DEFAULT_MARGIN_BOTTOM,
       float $marginLeft   = self::DEFAULT_MARGIN_LEFT,
       float $marginRight  = self::DEFAULT_MARGIN_RIGHT
   ) {
       // ...existing assignments...
       $this->marginRight = $marginRight;
   }
   ```

4. Add getter:
   ```php
   public function getMarginRight(): float { return $this->marginRight; }
   ```

5. Add method (analog of the renamed `printableHeight()` from item 6):
   ```php
   public function printableWidth(): float
   {
       return $this->width - $this->marginLeft - $this->marginRight;
   }
   ```

6. Rename existing method `usableHeight()` → `printableHeight()` for naming consistency with the new `printableWidth()`. The word "printable" is more precise for a reporting library targeting print output; keeping two names for one concept ("usable" and "printable") would be a permanent smell. Only one caller: `writer/src/Layout/LayoutService.php:33`, updated in the same commit. No external consumers of the old name (library is 0.1.0 with no downstream Packagist users).

## Acceptance criteria

- [x] `PageConfig` has `DEFAULT_MARGIN_RIGHT`, `marginRight` field, constructor param (default = 20.0), `getMarginRight()`, `printableWidth()`
- [x] Method `usableHeight()` renamed to `printableHeight()`; the one internal caller in `LayoutService` updated in the same commit
- [x] All existing library tests pass unchanged in behavior (constructor param default preserves backward compat; rename is transparent because no test asserted on the old method name)
- [x] New unit tests for `PageConfig` covering: default marginRight, custom marginRight override, `printableWidth()` returns `width - marginLeft - marginRight` for both default and custom values, `printableHeight()` still behaves the same after rename
- [x] `HtmlRenderer` unchanged — this ticket is PageConfig + one-line LayoutService caller update only. Consumer adoption of `printableWidth()` comes with Ticket 017 fix work.

## Non-goals

- Fixing Ticket 017 (title alignment) — this ticket unblocks it, doesn't fix it
- Adding right-side overflow detection to LayoutService — separate design question
- Modifying any consumer (`writer-app/`, `frontend/`) to use `printableWidth()` — deferred until a consumer needs it

## Related

- Unblocks [Ticket 017](017-title-alignment-vs-columns-vs-page.md) Options A + C
- Surfaced in the [Ticket 017](017-title-alignment-vs-columns-vs-page.md) filing during 2026-08-23 backlog sweep

## Implementation notes (2026-08-23)

- The original library method was `usableHeight()`. After a first-pass implementation added `printableHeight()` as an alias (introducing a two-names-for-one-concept smell), we opted instead to rename `usableHeight()` → `printableHeight()` and update the single internal caller in `LayoutService`. Naming symmetry with the new `printableWidth()` restored; no permanent alias smell; library still at 0.1.0 with no external consumers means the rename is a safe move.
- Full library suite: 117 → 122 tests. Zero behavior change for existing consumers.
