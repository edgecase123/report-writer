# Title Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix Ticket 017 by decoupling the title band from column widths. `ReportBuilder` emits the title element with `width = 0.0` (meaning "no declared width"); `LayoutService` substitutes `PageConfig::printableWidth()` at the point where it emits `PositionedElement`. Fill stays PageConfig-free; the renderer is unchanged; JSON template authors get `"width": 0` support for free.

**Architecture:** The Fill → Layout → Stream → Render pipeline gets one new convention: `ElementInstance::width == 0.0` is a sentinel meaning "no declared width; layout substitutes the printable page width." The sentinel exists only across the Fill→Layout boundary — every `PositionedElement` that reaches Stream/Render has a concrete positive width.

**Tech Stack:** PHP 7.4+, PHPUnit 9.5. Zero framework dependencies. Composer autoload.

**Spec:** [`docs/superpowers/specs/2026-08-23-title-alignment-design.md`](../specs/2026-08-23-title-alignment-design.md)

**Ticket:** [`docs/tickets/017-title-alignment-vs-columns-vs-page.md`](../../tickets/017-title-alignment-vs-columns-vs-page.md)

---

## File Structure

**Modified:**
- `writer/src/Layout/LayoutService.php` — adds a private `resolvedWidth()` helper; substitutes at `placeBand()` (1 site) and `splitAndPlace()` (2 sites).
- `writer/src/Instance/ElementInstance.php` — constructor docblock adds a one-line note on the `width = 0.0` sentinel.
- `writer/src/Builder/ReportBuilder.php` — title band constructor stops reading `$this->totalWidth()` and emits `width = 0.0`.
- `writer-app/src/Reports/DailySalesFiller.php` — revert Option-D workaround: columns go back to natural widths (120/120/120 at x=0/130/260).
- `docs/tickets/017-title-alignment-vs-columns-vs-page.md` — mark accepted; link the spec + plan.
- `docs/tickets/README.md` — flip 017 from open to closed in the ledger.
- `docs/architecture/element-width-sentinel.md` — new short doc capturing the `width = 0.0` convention and the Fill→Layout boundary rule.

**Modified (tests):**
- `writer/tests/Unit/Layout/LayoutServiceTest.php` — adds 3 tests (sentinel resolves; non-zero passes through; split path resolves).
- `writer/tests/Unit/Builder/ReportBuilderTest.php` — adds 1 test (title element has `width === 0.0`).

**Not modified:**
- `writer/src/Renderer/HtmlRenderer.php`, `writer/src/Renderer/JsonRenderer.php`, `writer/src/Stream/PositionedElement.php` — all consume concrete widths; sentinel never reaches them.
- `writer/src/Fill/DefinitionFiller.php` — JSON templates already declare widths explicitly; no bug to fix. Templates gain `"width": 0` capability for free via the Layout-side change.
- `writer-app/tests/Smoke/ReportRenderSmokeTest.php`, `writer-app/tests/Unit/Reports/DailySalesFillerTest.php` — neither asserts on column widths, so the revert is invisible to them. Verify green after the revert without touching.

---

## Task 0: Create feature branch

**Files:** none

- [ ] **Step 1: From `main`, cut a new branch**

```bash
cd /Users/leejenkins/dev/report-writer
git checkout main
git status                    # expected: working tree clean
git checkout -b feat/017-title-alignment-decouple
```

Expected: `Switched to a new branch 'feat/017-title-alignment-decouple'`

---

## Task 1: LayoutService substitutes width=0 with printableWidth (TDD)

Goal: `LayoutService::layout()` produces `PositionedElement` instances whose width is `pageConfig->printableWidth()` for any input element with `width === 0.0`, and passes non-zero widths through unchanged. Split-path (`splitAndPlace`) does the same.

**Files:**
- Modify: `writer/src/Layout/LayoutService.php` (add `resolvedWidth()`, use at 3 sites)
- Test: `writer/tests/Unit/Layout/LayoutServiceTest.php` (3 new tests)

- [ ] **Step 1: Write failing test #1 — width=0 resolves to printableWidth**

Add this test to `writer/tests/Unit/Layout/LayoutServiceTest.php` (append inside the class):

```php
public function testWidthZeroElementResolvesToPrintableWidth(): void
{
    // PageConfig: width=612, marginLeft=20, marginRight=20 → printableWidth=572
    $service = new LayoutService(
        new Flattener(),
        new PageConfig(612.0, 792.0, 20.0, 20.0, 20.0, 20.0)
    );

    $el     = new ElementInstance('sentinel', 0.0, 0.0, 0.0, 20.0, new TextContent('X'));
    $report = new ReportInstance('r1', [new BandInstance('b1', 'title', [$el])]);

    $positioned = $service->layout($report)->getPages()[0]->getElements()[0];

    $this->assertSame(572.0, $positioned->getWidth());
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd /Users/leejenkins/dev/report-writer/writer
vendor/bin/phpunit --filter testWidthZeroElementResolvesToPrintableWidth
```

Expected: FAIL. The assertion should report `Failed asserting that 0.0 is identical to 572.0.` (Layout currently passes the width through as-is.)

- [ ] **Step 3: Write failing test #2 — non-zero widths pass through unchanged**

Append to the same file:

```php
public function testNonZeroWidthPassesThroughUnchanged(): void
{
    $service = new LayoutService(
        new Flattener(),
        new PageConfig(612.0, 792.0, 20.0, 20.0, 20.0, 20.0)
    );

    $el     = new ElementInstance('fixed', 0.0, 0.0, 300.0, 20.0, new TextContent('X'));
    $report = new ReportInstance('r1', [new BandInstance('b1', 'detail', [$el])]);

    $positioned = $service->layout($report)->getPages()[0]->getElements()[0];

    $this->assertSame(300.0, $positioned->getWidth());
}
```

- [ ] **Step 4: Run test #2 to verify it currently passes**

```bash
cd /Users/leejenkins/dev/report-writer/writer
vendor/bin/phpunit --filter testNonZeroWidthPassesThroughUnchanged
```

Expected: PASS. This is a regression guard for the substitution logic — it must not fire on non-zero widths. Confirming it passes before the change ensures the change doesn't break it.

- [ ] **Step 5: Write failing test #3 — split-path resolves the sentinel too**

Append to the same file:

```php
public function testSplittableWidthZeroResolvesOnBothChunks(): void
{
    // Page usable = 30; text has lineHeight=10 so 3 lines fit per page.
    // Content is 4 lines → split: 3 on p1, 1 on p2. Both chunks must get resolved width.
    $service = new LayoutService(
        new Flattener(),
        new PageConfig(612.0, 30.0, 0.0, 0.0, 20.0, 20.0)  // printableWidth=572
    );
    $content = new TextContent("L1\nL2\nL3\nL4", 10.0);
    $el      = new ElementInstance('el1', 0.0, 0.0, 0.0, 40.0, $content);

    $stream = $service->layout(new ReportInstance('r1', [new BandInstance('b1', 'detail', [$el])]));
    $pages  = $stream->getPages();

    $this->assertCount(2, $pages);
    $this->assertSame(572.0, $pages[0]->getElements()[0]->getWidth(), 'first-chunk width must be resolved');
    $this->assertSame(572.0, $pages[1]->getElements()[0]->getWidth(), 'continuation-chunk width must be resolved');
}
```

- [ ] **Step 6: Run test #3 to verify it fails**

```bash
cd /Users/leejenkins/dev/report-writer/writer
vendor/bin/phpunit --filter testSplittableWidthZeroResolvesOnBothChunks
```

Expected: FAIL. Both chunks currently carry width=0.

- [ ] **Step 7: Implement the change in `LayoutService`**

Open `writer/src/Layout/LayoutService.php`. Add this private helper directly after the existing `bandHeight()` method (after line 83):

```php
    /**
     * Resolves an element's effective width. A sentinel value of 0.0 means
     * "no declared width" — the abstract-vs-physical boundary between Fill
     * and Layout. Fill declares intent; Layout substitutes the concrete
     * printable page width. All PositionedElement instances leaving
     * LayoutService carry concrete positive widths.
     */
    private function resolvedWidth(ElementInstance $el): float
    {
        return $el->getWidth() ?: $this->pageConfig->printableWidth();
    }
```

You will also need to add the import for `ElementInstance` at the top of the file. Check the existing use-statement block (around lines 6-13); if `ElementInstance` is not already imported, add:

```php
use ReportWriter\Instance\ElementInstance;
```

- [ ] **Step 8: Wire `resolvedWidth()` into `placeBand()`**

In `writer/src/Layout/LayoutService.php`, replace the current `placeBand()` (lines 100-116) with:

```php
    private function placeBand(BandInstance $band, float $cursorY, Page $page): void
    {
        $marginLeft = $this->pageConfig->getMarginLeft();
        $bandType   = $band->getBandType();
        foreach ($band->getElements() as $el) {
            $page->addElement(new PositionedElement(
                $el->getInstanceId(),
                $marginLeft + $el->getX(),
                $cursorY + $el->getY(),
                $this->resolvedWidth($el),
                $el->getHeight(),
                $el->getContent(),
                $bandType,
                $el->getTextAlign()
            ));
        }
    }
```

Only line 109 changed: `$el->getWidth()` → `$this->resolvedWidth($el)`.

- [ ] **Step 9: Wire `resolvedWidth()` into `splitAndPlace()`**

In the same file, `splitAndPlace()` emits two `PositionedElement` instances (currently at lines 140-149 and 159-168). Replace `$element->getWidth()` at both sites with `$this->resolvedWidth($element)`. The two lines to change:

Line 144: change `$element->getWidth(),` → `$this->resolvedWidth($element),`
Line 163: change `$element->getWidth(),` → `$this->resolvedWidth($element),`

- [ ] **Step 10: Run all three new tests + the whole layout suite**

```bash
cd /Users/leejenkins/dev/report-writer/writer
vendor/bin/phpunit tests/Unit/Layout/
```

Expected: all tests PASS, including the three new ones and every existing LayoutService/Flattener/PageConfig test.

- [ ] **Step 11: Run the full library test suite (regression guard)**

```bash
cd /Users/leejenkins/dev/report-writer/writer
vendor/bin/phpunit
```

Expected: `OK (161 tests, ...)`. The 158 previously-green tests remain green, plus the 3 new tests. If any pre-existing test breaks, stop and investigate — the change should be transparent to any test whose elements have concrete non-zero widths.

- [ ] **Step 12: Commit**

```bash
cd /Users/leejenkins/dev/report-writer
git add writer/src/Layout/LayoutService.php writer/tests/Unit/Layout/LayoutServiceTest.php
git commit -m "$(cat <<'EOF'
feat(layout): resolve width=0 sentinel to printableWidth at PositionedElement emission

LayoutService gains a private resolvedWidth() helper called from placeBand()
and both PositionedElement emission sites in splitAndPlace(). Elements with
width=0 (the "no declared width" sentinel) receive PageConfig::printableWidth()
before crossing into Stream. Non-zero widths pass through unchanged.

Part of Ticket 017 (title alignment decoupling).
EOF
)"
```

---

## Task 2: Document the sentinel on `ElementInstance`

Goal: The docblock on `ElementInstance::__construct` names the sentinel so future readers of the type see the convention without hunting through Layout.

**Files:**
- Modify: `writer/src/Instance/ElementInstance.php`

- [ ] **Step 1: Add the docblock**

Open `writer/src/Instance/ElementInstance.php`. Replace the current `__construct` (lines 19-27) with:

```php
    /**
     * @param float $width Width in points. Pass 0.0 to declare no width; LayoutService
     *                     will substitute PageConfig::printableWidth() when placing
     *                     the element. Non-zero widths are used as-is.
     */
    public function __construct(
        string $instanceId,
        float $x,
        float $y,
        float $width,
        float $height,
        ElementContent $content,
        string $textAlign = ''
    ) {
        $this->instanceId = $instanceId;
        $this->x          = $x;
        $this->y          = $y;
        $this->width      = $width;
        $this->height     = $height;
        $this->content    = $content;
        $this->textAlign  = $textAlign;
    }
```

- [ ] **Step 2: Verify tests still pass (docs-only, but confirm)**

```bash
cd /Users/leejenkins/dev/report-writer/writer
vendor/bin/phpunit
```

Expected: still green (161 tests).

- [ ] **Step 3: Commit**

```bash
cd /Users/leejenkins/dev/report-writer
git add writer/src/Instance/ElementInstance.php
git commit -m "docs(instance): note width=0 sentinel semantics on ElementInstance ctor"
```

---

## Task 3: ReportBuilder emits title with width=0 (TDD)

Goal: `ReportBuilder::title()` builds the title band without reading `$this->columns`. Its single element has `width === 0.0`.

**Files:**
- Modify: `writer/src/Builder/ReportBuilder.php` (delete `$totalWidth` line; change argument in title element constructor)
- Test: `writer/tests/Unit/Builder/ReportBuilderTest.php` (add 1 test)

- [ ] **Step 1: Write the failing test**

Append this test to `writer/tests/Unit/Builder/ReportBuilderTest.php` (inside the class):

```php
public function testTitleElementDeclaresNoWidth(): void
{
    // Title element should carry the width=0 sentinel so LayoutService
    // substitutes printable page width. This guards against any future
    // re-introduction of cross-band coupling (e.g. reading from $this->columns).
    $bands  = $this->baseBuilder()->title('My Report')->build()->getBandInstances();
    $title  = $bands[0];
    $this->assertSame('band_title', $title->getBandInstanceId());

    $elements = $title->getElements();
    $this->assertCount(1, $elements);
    $this->assertSame(0.0, $elements[0]->getWidth(),
        'title element must declare width=0 (no coupling to columns extent)');
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd /Users/leejenkins/dev/report-writer/writer
vendor/bin/phpunit --filter testTitleElementDeclaresNoWidth
```

Expected: FAIL. Assertion should report `Failed asserting that 300.0 is identical to 0.0.` (Current baseBuilder columns sum to 300pt via `itemCol` + `totalCol`.)

- [ ] **Step 3: Modify `ReportBuilder::build()` to remove the coupling**

Open `writer/src/Builder/ReportBuilder.php`. Locate the title-band construction (lines 168-177). Replace with:

```php
        if ($this->titleText !== null) {
            $titleBand  = new BandInstance('band_title', 'title', [
                new ElementInstance('title', 0.0, 0.0, 0.0, $this->titleHeight, new TextContent($this->titleText)),
            ]);
            $titleBand = $this->applyBandCallbacks('title', $titleBand, new BandContext([], null, [], []));
            if ($titleBand !== null) {
                $bands[] = $titleBand;
            }
        }
```

Only two things changed relative to the current code:
- The `$totalWidth = $this->totalWidth();` line is deleted.
- The 4th positional argument to `ElementInstance::__construct` (width) is now `0.0` instead of `$totalWidth`.

The rest of `build()` (col-header, groups, detail, summary) and the `totalWidth()` private helper method are unchanged — group-header bands at lines 339-350 still call `totalWidth()`, and that is deliberately out of scope for this ticket.

- [ ] **Step 4: Run the new test to verify it passes**

```bash
cd /Users/leejenkins/dev/report-writer/writer
vendor/bin/phpunit --filter testTitleElementDeclaresNoWidth
```

Expected: PASS.

- [ ] **Step 5: Run the full library suite (regression guard)**

```bash
cd /Users/leejenkins/dev/report-writer/writer
vendor/bin/phpunit
```

Expected: `OK (162 tests, ...)`. Pay attention to any prior tests that inspected the title element's width — if any exist, they will now fail with `0.0` instead of the old columns-derived value. If that happens, treat it as expected and update the test's assertion to match `0.0` (that's the new, correct value).

- [ ] **Step 6: Commit**

```bash
cd /Users/leejenkins/dev/report-writer
git add writer/src/Builder/ReportBuilder.php writer/tests/Unit/Builder/ReportBuilderTest.php
git commit -m "$(cat <<'EOF'
feat(builder): title band emits width=0; drop coupling to columns extent

ReportBuilder::build() no longer calls $this->totalWidth() when constructing
the title band. The title element declares width=0 (the "no declared width"
sentinel); LayoutService substitutes PageConfig::printableWidth() at
PositionedElement emission time.

Kills the cross-band coupling flagged in Ticket 017: the title band was
being sized by another band's contents (columns extent), which produced
off-center titles whenever columns didn't span the printable area.

Group-header bands still call totalWidth() at ReportBuilder.php:339;
that is a separate follow-up.
EOF
)"
```

---

## Task 4: End-to-end regression test — narrow-column report title stretches to printable width

Goal: An integration-style test that builds a `ReportBuilder` report with narrow columns and asserts the title `PositionedElement` (post-layout) has width=572 and x=20 (marginLeft) on default Letter. This is the durable guard against the exact visual bug that motivated the ticket.

**Files:**
- Test: `writer/tests/Unit/Layout/LayoutServiceTest.php` (add 1 test)

- [ ] **Step 1: Write the failing test**

Append this test to `writer/tests/Unit/Layout/LayoutServiceTest.php`. It needs new imports: `Column` and `ReportBuilder`. Add to the file's `use` block near the top:

```php
use ReportWriter\Builder\Column;
use ReportWriter\Builder\ReportBuilder;
```

Then append the test:

```php
public function testReportBuilderNarrowColumnsTitleStretchesToPrintableWidth(): void
{
    // Regression test for Ticket 017.
    // Build a report whose columns span only 380pt on a Letter page
    // (printable width = 572pt). Assert the title's PositionedElement
    // occupies the full printable area — width=572, x=20 (marginLeft).
    $service = new LayoutService(
        new Flattener(),
        new PageConfig(612.0, 792.0, 20.0, 20.0, 20.0, 20.0)
    );

    $report = ReportBuilder::create('narrow_report')
        ->title('Narrow')
        ->columns([
            Column::make('a', 'A', 0.0,   120.0),
            Column::make('b', 'B', 130.0, 120.0),
            Column::make('c', 'C', 260.0, 120.0),
        ])
        ->build();

    $positioned = $service->layout($report)->getPages()[0]->getElements();

    // The first PositionedElement in the stream is the title element
    // (title band placed at the top of the first page).
    $this->assertSame('title', $positioned[0]->getInstanceId());
    $this->assertSame(20.0,    $positioned[0]->getX(),     'title x must equal marginLeft');
    $this->assertSame(572.0,   $positioned[0]->getWidth(), 'title width must equal printableWidth');
}
```

- [ ] **Step 2: Run the test to verify it passes**

```bash
cd /Users/leejenkins/dev/report-writer/writer
vendor/bin/phpunit --filter testReportBuilderNarrowColumnsTitleStretchesToPrintableWidth
```

Expected: PASS (Tasks 1 + 3 have already delivered the behavior; this test locks it end-to-end).

If it FAILS, either Task 1 or Task 3 has a defect. Investigate before proceeding.

- [ ] **Step 3: Run the full library suite one more time**

```bash
cd /Users/leejenkins/dev/report-writer/writer
vendor/bin/phpunit
```

Expected: `OK (163 tests, ...)`.

- [ ] **Step 4: Commit**

```bash
cd /Users/leejenkins/dev/report-writer
git add writer/tests/Unit/Layout/LayoutServiceTest.php
git commit -m "test(layout): end-to-end regression — narrow-column title stretches to printable width"
```

---

## Task 5: Revert the DailySalesFiller Option-D workaround

Goal: `writer-app/src/Reports/DailySalesFiller.php` goes back to natural column widths (120/120/120 at x=0/130/260). The library fix now handles the title alignment; the demo no longer needs to widen columns to compensate.

**Files:**
- Modify: `writer-app/src/Reports/DailySalesFiller.php`

- [ ] **Step 1: Revert the column widths**

Open `writer-app/src/Reports/DailySalesFiller.php`. Locate the `columns([...])` block (lines 31-38). Replace with:

```php
            ->columns([
                Column::make('order_id',    'Order',       0,   120),
                Column::make('closed_at',   'Closed',      130, 120),
                Column::make('total_cents', 'Total',       260, 120)
                    ->sum()
                    ->alignRight()
                    ->format($currency),
            ])
```

Only the numeric column widths and x-offsets change (back to the pre-workaround values from commit `3f94c48`).

- [ ] **Step 2: Run the writer-app test suite**

```bash
cd /Users/leejenkins/dev/report-writer/writer-app
vendor/bin/phpunit
```

Expected: `OK (30 tests, 61 assertions)`. No test asserts on column widths, so the revert is transparent — but confirm.

- [ ] **Step 3: Commit**

```bash
cd /Users/leejenkins/dev/report-writer
git add writer-app/src/Reports/DailySalesFiller.php
git commit -m "$(cat <<'EOF'
revert(demo): restore natural Daily Sales column widths (Ticket 017 workaround no longer needed)

Task 16 of the A2 followup sweep widened Daily Sales columns to sum to 572pt
so that ReportBuilder::title()'s columns-extent-centered title would visually
land at page center. With the library-side fix from Ticket 017, the title
stretches to printableWidth naturally; columns can return to their natural
120pt widths.

Reverts the numeric changes from 2ccdab4.
EOF
)"
```

---

## Task 6: Close Ticket 017 in the docs; add architecture note

Goal: The ticket ledger reflects that 017 is done. A short architecture doc captures the durable `width = 0.0` convention so future contributors (and future potential decouplings of group-header) see the precedent.

**Files:**
- Modify: `docs/tickets/017-title-alignment-vs-columns-vs-page.md`
- Modify: `docs/tickets/README.md`
- Create: `docs/architecture/element-width-sentinel.md`

- [ ] **Step 1: Update Ticket 017 status**

Open `docs/tickets/017-title-alignment-vs-columns-vs-page.md`. Change the header from:

```markdown
**Status:** Open — design decision required (breaking-vs-non-breaking trade-off)
```

to:

```markdown
**Status:** ✅ Closed — resolved via `width = 0.0` sentinel + Layout substitution
```

At the bottom of the same file (after the "Related" section), append:

```markdown
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
```

- [ ] **Step 2: Flip 017 in the ticket ledger**

Open `docs/tickets/README.md`. The ledger is a single markdown table (all tickets in one table with a Status column — there are no separate open/closed sections). Find the row for Ticket 017 (around line 27). The Status cell currently reads `Open`. Change it to `✅ Closed (2026-08-23)`, matching the format used by Ticket 018 on the next row. Nothing else on the row changes — same title, same priority, same source columns.

- [ ] **Step 3: Write the architecture note**

Create `docs/architecture/element-width-sentinel.md` with:

```markdown
# Element Width Sentinel

## Convention

`ReportWriter\Instance\ElementInstance::width` uses `0.0` as a sentinel meaning
**"no declared width; Layout will substitute the printable page width."**

`ReportWriter\Layout\LayoutService` reads the sentinel exactly once, at the
point where each `ElementInstance` becomes a `PositionedElement`, and
substitutes `PageConfig::printableWidth()`. Any element whose width is a
non-zero float is used as-is.

## Why

The pipeline is Fill → Layout → Stream → Render. `Fill` (ReportBuilder,
DefinitionFiller, custom fillers) authors against an abstract staging area.
It does not know about `PageConfig`, physical margins, or paper size — that
knowledge lives exclusively in Layout.

Historically, `ReportBuilder::title()` reached into `$this->columns` to derive
the title element's width from the columns' extent. That coupling meant one
band's element was sized by another band's contents, and it produced
off-center titles whenever columns didn't span the printable area
(see [Ticket 017](../tickets/017-title-alignment-vs-columns-vs-page.md)).

The `width = 0.0` sentinel is Fill's way of saying "I have no opinion; use
the staging area." Layout, which owns `PageConfig`, translates that intent
into the concrete printable width. The sentinel exists only across the
Fill → Layout boundary — every `PositionedElement` that reaches Stream and
Renderer has a concrete positive width.

## Scope

Today, only the title band emitted by `ReportBuilder` uses the sentinel.
Group-header bands still call `totalWidth()`; if that ever gets decoupled,
the same sentinel is the natural mechanism.

`DefinitionFiller` (the JSON-driven filler) does not use the sentinel by
default — template authors declare element widths explicitly in JSON — but
they can opt in by setting `"width": 0` on any element definition. The
Layout-side substitution fires the same way regardless of the filler that
produced the element.
```

- [ ] **Step 4: Verify no tests broke (should not — docs only)**

```bash
cd /Users/leejenkins/dev/report-writer/writer && vendor/bin/phpunit
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit
```

Expected: both suites green (163 in `writer`, 30 in `writer-app`).

- [ ] **Step 5: Commit**

```bash
cd /Users/leejenkins/dev/report-writer
git add docs/tickets/017-title-alignment-vs-columns-vs-page.md docs/tickets/README.md docs/architecture/element-width-sentinel.md
git commit -m "docs: close Ticket 017; add element-width-sentinel architecture note"
```

---

## Task 7: Full-suite verification + end-to-end demo check

Goal: One last pass to confirm both test suites are green together, then bring the demo up and eyeball the Daily Sales report to verify the title visually centers on the page.

- [ ] **Step 1: Run both test suites in sequence**

```bash
cd /Users/leejenkins/dev/report-writer/writer && vendor/bin/phpunit
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit
```

Expected:
- `writer`: `OK (163 tests, ...)` (baseline 158 + 3 layout + 1 builder + 1 e2e regression = 163)
- `writer-app`: `OK (30 tests, 61 assertions)`

If either fails, stop and diagnose before pushing.

- [ ] **Step 2: Bring the demo up**

```bash
cd /Users/leejenkins/dev/report-writer
./scripts/demo-up
```

Expected: Docker containers start; script prints the URLs (`http://localhost:8090/...`).

- [ ] **Step 3: Verify Daily Sales title visually centers**

Open `http://localhost:8090/api/reports/daily-sales?date=2026-08-22` in a browser.

Expected visual result: the "Daily Sales — 2026-08-22" title is centered on the printable page area, not centered above the (now-narrower) column extent. The columns should be visibly narrower than before the ticket work landed.

If the title does NOT visually center, something in the change chain (Task 1, 3, or 5) has a defect. Stop and diagnose.

- [ ] **Step 4: Bring the demo down**

```bash
cd /Users/leejenkins/dev/report-writer
docker compose down
```

- [ ] **Step 5: No commit needed for Task 7** — verification only.

---

## Task 8: Push branch and open the PR

**Files:** none (git + `gh` operations)

- [ ] **Step 1: Push the branch**

```bash
cd /Users/leejenkins/dev/report-writer
git push -u origin feat/017-title-alignment-decouple
```

- [ ] **Step 2: Open the PR**

```bash
gh pr create --title "feat(writer): decouple title band from columns (Ticket 017)" --body "$(cat <<'EOF'
## Summary

- `ReportBuilder::title()` no longer derives width from `$this->columns`. Title element declares `width = 0.0` (the "no declared width" sentinel).
- `LayoutService` substitutes `PageConfig::printableWidth()` for `width = 0.0` at the point where each `ElementInstance` becomes a `PositionedElement`. Applied at all three emission sites (`placeBand` + both chunks in `splitAndPlace`).
- Reverts the Option-D column-widening workaround in `DailySalesFiller` — natural per-column widths now render correctly.
- Adds `docs/architecture/element-width-sentinel.md` documenting the durable convention.

## Design docs

- Spec: `docs/superpowers/specs/2026-08-23-title-alignment-design.md`
- Plan: `docs/superpowers/plans/2026-08-23-title-alignment-implementation.md`
- Ticket: `docs/tickets/017-title-alignment-vs-columns-vs-page.md`

## Test plan

- [ ] `cd writer && vendor/bin/phpunit` — expect 163 green (baseline 158 + 5 new)
- [ ] `cd writer-app && vendor/bin/phpunit` — expect 30 green (no change; verifies revert is transparent)
- [ ] `./scripts/demo-up` → open `http://localhost:8090/api/reports/daily-sales?date=2026-08-22` → title visually centers on the printable page (not on the narrower columns extent)
- [ ] Group-header bands still render normally (they continue to use `totalWidth()`; this ticket does not touch them)

## Non-goals

- Decoupling group-header from `totalWidth()` — separate ticket if we ever want it.
- Adding `PageConfig` awareness to `ReportBuilder` — the builder remains PageConfig-free.
- New fluent methods on `ReportBuilder` — no `->titleWidth()`, no `->titleAlignment()`. The whole fix is achieved by *removing* the coupling.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

Return the PR URL when done.

---

## Post-plan checklist

After Task 8 merges:

- Update `~/context/report-writer/status.md` to reflect: Ticket 017 shipped; branch merged; test counts updated (writer 163, writer-app 30).
- Announce the next open decision: A3 planning vs. picking from remaining open backlog (003/004/005 library refactors, 007/008/009/011 frontend).
