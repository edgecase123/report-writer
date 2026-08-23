---
title: What is a report?
updated: 2026-08-22
covers:
  - writer/src/Instance/ReportInstance.php
  - writer/src/Instance/BandInstance.php
  - writer/src/Instance/ElementInstance.php
  - writer/src/Layout/PageConfig.php
---

# What is a report?

Reporting has its own vocabulary. If you've never built a report before, or you've built HTML tables and think that's the same thing, this page is the ground floor. Everything else in the docs assumes what's on this page.

---

## The everyday example

You've seen reports. A monthly bank statement. A restaurant end-of-day sales summary. A grade report from school. They all share the same shape:

```
                MERCHANDISE SALES REPORT               ← title
                August 22, 2026

DATE       ITEM              QTY   PRICE     TOTAL   ← column headers
─────────────────────────────────────────────────────
Aug 22   Espresso Blend      3    $18.00    $54.00   ← detail rows
Aug 22   Iced Latte          1    $6.50     $6.50    │  one per data row
Aug 22   Blueberry Muffin    2    $3.75     $7.50    ▼
Aug 22   Cold Brew           1    $5.25     $5.25
─────────────────────────────────────────────────────
                          GRAND TOTAL:      $73.25   ← summary
```

Four visible sections stacked vertically:

1. A **title** — describes what the report is
2. **Column headers** — say what each column contains
3. **Detail rows** — one row per underlying data record, all with the same shape (same columns, same alignment)
4. A **summary** — grand totals, averages, whatever the report needs at the bottom

This is the fundamental shape report-writer produces. Everything else — grouping, subreports, page splitting — is layered on top of it.

---

## Why this isn't just an HTML table

You could produce the same visual output as a `<table>` with some CSS. And for on-screen display, that's often fine. But there are four things HTML tables don't do well that reports need to do exactly:

### 1. Pagination

An HTML table lets the browser decide where page breaks fall when you print. The result: rows split mid-height, column headers appear only on page 1, the "grand total" ends up alone on the last page with no context.

Reports need to know: after N rows, this page is full; start a new page; where relevant, repeat the column headers on subsequent pages; keep group headers near their rows; put the grand total on the last page. That's **layout logic**, not visual styling — decided by code, not by the browser's CSS engine.

report-writer runs its own layout pass that decides where every page break falls. A report of 5 rows fits on one page and a report of 500 rows fits on 12 pages, with every page break at a location the library chose deliberately. *(The v1 target adds repeating column headers, keep-with-next / keep-with-first-detail rules, and forced page breaks; today's library implements the base pagination — see [Architecture Specifications](../architecture/) for the target.)*

### 2. Alignment across pages

Numeric columns need their decimal points to line up. If a column shows dollar amounts, `$5.25` and `$54.00` should align to their `.`, not to their left edge. On a single page, CSS handles that fine. Across page boundaries, HTML tables lose the alignment — the browser doesn't know the second page's numbers should line up with the first page's.

report-writer positions every cell at an absolute `(x, y, width, height)` in **points** (72 pt = 1 inch), so `$5.25` on page 1 and `$54.00` on page 3 are at the same `x`, `width`, and `text-align: right`. The decimal points line up because the math says they do.

### 3. Repeatable exact output

Two runs of the same report with the same input data should produce identical output down to the character position. That property is called **determinism**, and it matters because:

- You can snapshot-test a report — assert that the rendered HTML equals a known-good HTML string
- You can compare two runs across time and see exactly what changed
- The on-screen preview at 100% zoom is byte-identical to what prints

HTML + CSS rendering depends on the browser's font metrics, its layout engine version, the operating system's rendering hints. Two Chrome installs on two machines can produce visually-different tables from the same HTML. Reports need to be deterministic; the browser is not.

report-writer computes positions in PHP and hands the browser a document where every element already has `position: absolute; left: 42pt; top: 128pt;`. The browser's only job is to draw text where told.

### 4. Print parity

The output on paper should match what you see on screen. Not "roughly the same layout" — the same layout, at the same scale. `@media print` in report-writer's HTML output is designed to produce paper output identical to the on-screen preview at 100% zoom. You get "what you see is what prints" without fighting the browser's print CSS.

---

## The core vocabulary

To read the rest of the docs (and the code), you need these five terms. Everything is built on them.

### Report

The whole thing. A `ReportInstance` object represents one report, fully filled with data, ready to lay out. Every report has an ID (`'daily-sales'`), and a list of **bands** stacked vertically.

### Band

A horizontal slice of the report. Every band has a **type** that tells the layout engine how to treat it:

| Type | What it is |
|---|---|
| `title` | The top of the report |
| `col-header` | Column names ("DATE", "ITEM", "QTY", ...) — laid out once at the top of the first page today; the v1 target supports repeating on subsequent pages |
| `detail` | One row of data from the underlying source |
| `group-header` | The header for a group of rows (e.g. "ESPRESSO" before all espresso items) |
| `group-footer` | The footer for a group (e.g. "Espresso subtotal: $124.00") |
| `summary` | The grand total at the bottom |

A band knows nothing about its position — only that it's a band of a certain type containing some elements.

### Element

Everything visible is an element. A column header is one element per column. A detail row is one element per column. A title is one element that spans the width of the page.

Every element has:
- An `(x, y, width, height)` in points, relative to the band's origin (not the page)
- A **content** — the actual value to display (a string, a formatted number, an aggregate)
- An optional text alignment (left / right / centre)

### Content

The value inside an element. Content can be one of four kinds:

| Kind | Example |
|---|---|
| **Static text** | Literal string, unchanged. `"GRAND TOTAL"` |
| **Field value** | Value from the current row. `row['amount']` |
| **Aggregate** | Computed over rows in scope. `SUM(amount)`, `COUNT(*)` |
| **Computed** | Arbitrary PHP callable given access to the row + params |

Deep dive in [Expressions and formatters](expressions-and-formatters.md) (planned).

### Page

After layout, the report is split into pages. Each page is a list of **positioned elements** — every element from every band that ended up on that page, with its absolute `(x, y)` computed from the band's position plus the element's in-band offset.

A page is what a renderer emits. HTML renders one page as one `<div class="rw-page">`; JSON renders one page as one array of positioned-element objects.

---

## Why points, not pixels

Reports are print-first. Paper sizes are defined in inches (US Letter = 8.5" × 11") or millimetres (A4 = 210 mm × 297 mm). Screen pixels are variable — different displays, different zoom levels, different DPI.

**Points** are the traditional print-industry unit: 1 point = 1/72 of an inch. US Letter is 612 × 792 points. A 12-point font is 12/72" tall regardless of device.

report-writer uses points throughout. Coordinates, widths, heights, margins — all in points. This gives you two things:

1. **The math is exact.** A column at `x = 100 pt, width = 82 pt` ends at `x = 182 pt` on every device.
2. **The on-screen preview is honest.** The viewer's zoom control scales points to screen pixels at whatever ratio the user picks. At 100% zoom, one point = one CSS pixel; at 200%, one point = two CSS pixels. Print output is always at true 1:1, so nothing scales differently between preview and paper.

---

## Coordinate system

Origin is **top-left**. `x` increases to the right, `y` increases downward. This is the same convention as PDF, PostScript, HTML `position: absolute`, and pretty much every 2D screen system except mathematical Cartesian graphs.

```
(0, 0) ───────────────────► x
   │
   │        ┌──────────┐
   │        │ ELEMENT  │
   │        │  (x=100, │
   │        │   y=50,  │
   │        │   w=200, │
   │        │   h=30)  │
   │        └──────────┘
   ▼
   y
```

An element's `(x, y)` in the code is *its top-left corner*, relative to whatever it's positioned in (the band, for elements; the page, for positioned elements).

---

## The default page

`Layout/PageConfig` — US Letter, 612 × 792 pt (8.5" × 11" at 72 dpi), 20 pt margins on top, bottom, and left. Usable area is about 572 pt wide by 752 pt tall.

Change the page size by passing a different `PageConfig` to `LayoutService`. Every dimension in the code is a `float` in points, so switching to A4 (595.28 × 841.89 pt) is a one-line construction.

---

## Where reports come from

A report doesn't materialize on its own. Something has to:

1. Decide what report to run (a URL route, a CLI command)
2. Gather the parameters (a date, an ID, a range)
3. Look up the data (a SQL query, a REST call, a file read)
4. Package the data plus layout into a `ReportInstance`

Steps 1–2 are **the outer app** — a Slim route in this project, a Symfony controller in the foreUP outer app, a Laravel job somewhere else. Step 3 is a **data source** implementing `ReportDataSourceInterface`. Step 4 is a **filler** implementing `ReportFillerInterface`.

Together, steps 3 and 4 are what report-writer calls "fill" — turning params into a `ReportInstance`. Then layout, stream, and render take over. Deep dive: [Pipeline → Overview](../04-pipeline/overview.md) (planned).

---

## Common pitfalls (for someone new to reporting)

**"Why not just use a `<table>`?"** — Because pagination, cross-page alignment, and determinism aren't table features. If you don't need any of those, a `<table>` is fine and you don't need report-writer. If you print reports, do exports, or want snapshot tests, you need the layout to be code, not CSS.

**"Can I put images or charts in a report?"** — Not with the shipped renderers. Charts belong upstream — pre-render an SVG, drop it into a static-text element as an inline SVG. Support for image elements is a plausible future extension, but not shipped today.

**"Do I need to lay out every element by hand?"** — No. For the common tabular case, `ReportBuilder` takes columns (each with an `x` and `width`) and rows, and generates the bands and elements for you. Manual layout is for cases the builder can't express — see [Authoring → Custom filler](../05-authoring/custom-filler.md) (planned).

**"What if my report doesn't fit on a page?"** — It shouldn't; that's what pagination is for. What CAN'T fit is a single non-splittable band larger than a page (e.g. an element that's 900 pt tall on an 8.5×11 US Letter page). Layout throws `ElementExceedsPageException` in that case with the element's ID, so you can fix it. Multi-line text auto-splits at line boundaries.

**"How do I know what my report looks like without running it in a browser?"** — Two ways: (a) render to HTML in a test and assert against known-good output (`writer/tests/Unit/Renderer/HtmlRendererTest.php` shows the pattern), or (b) render to JSON and inspect the `PositionedElement` list programmatically. The whole point of the pipeline being deterministic is that you don't need a browser to know it's right.

---

## Source files

| File | Role |
|---|---|
| `writer/src/Instance/ReportInstance.php` | The "whole report" — an ID + a list of bands |
| `writer/src/Instance/BandInstance.php` | A band — a type + a list of elements |
| `writer/src/Instance/ElementInstance.php` | An element — position, size, content, alignment |
| `writer/src/Layout/PageConfig.php` | Page dimensions and margins in points |

## Related docs

- [Bands, elements, and content](bands-elements-and-content.md) — *(planned)* the primitives in more depth
- [Expressions and formatters](expressions-and-formatters.md) — *(planned)* how cell content is computed
- [Pipeline → Overview](../04-pipeline/overview.md) — *(planned)* how a report becomes rendered output
- [Authoring → Overview](../05-authoring/overview.md) — *(planned)* the three ways to write a report
