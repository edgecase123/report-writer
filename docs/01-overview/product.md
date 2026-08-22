---
title: Product Overview
updated: 2026-08-22
covers:
  - writer/src/ReportingPipeline.php
  - writer/src/Interfaces/ReportFillerInterface.php
  - writer/src/Interfaces/ReportDataSourceInterface.php
---

# Product Overview

report-writer is a deterministic reporting pipeline: it turns tabular row data (a database query result, an API response, an in-memory array) into pixel-honest paginated reports that render identically on screen and on paper.

---

## What it does

Feed it rows and a layout — get back HTML or JSON, split into pages, positioned to the point (72 pt = 1 inch), ready to print.

A **report** in this system is a specific arrangement of data:

- A **title** at the top
- **Column headers**
- **Detail rows** — one per input row
- Optional **group headers and footers** — for reports grouped by a field (e.g. "sales by category" groups rows by category, one header per category and a subtotal footer)
- A **summary** at the bottom — grand totals, averages, counts

Every visible element sits at an absolute (x, y, width, height) position in points, computed by the pipeline. The output is print-first: 8.5" × 11" US Letter pages by default, with an on-screen viewer that scales for review.

## Who uses it

| Role | Uses it for |
|---|---|
| **A developer building a business report** | Wraps a database query in a `ReportDataSourceInterface`, defines columns (in PHP or in a JSON template), gets a printable report back |
| **A business analyst editing report layout** | Uses the split-screen builder UI to edit a JSON template and see the result live |
| **An end user viewing a report** | Opens the viewer in a browser, sees the report at 100% or zoomed, hits Print to send it to the printer |

There is no "author reports without writing code" MVP — a developer sets up the data sources and the outer app; from there, JSON templates let non-developers iterate on layout.

## What problem it solves

Most report generation stacks force one of two bad trade-offs:

- **HTML-first layout** (a table with CSS) — renders great on screen; prints inconsistently, page breaks fall wherever the browser feels like, headers don't repeat, aggregates don't align across pages.
- **PDF-first layout** (a library like DomPDF, FPDF, or wkhtmltopdf) — prints accurately but tests are opaque, small changes require regenerating and eyeballing a PDF, and there's no clean intermediate representation to inspect or snapshot.

report-writer sits in between: the intermediate representation is real code (`ReportInstance` → `ReportStream` → `Page[]` of `PositionedElement`) that you can assert against in unit tests, and the HTML renderer produces output whose `@media print` rules match the on-screen preview exactly. The tests know the report was laid out correctly *without* rasterizing anything.

## What it is not

- **Not a chart library.** Bars, lines, pies, sparklines — out of scope. Report-writer emits text and boxes only. Charts belong upstream (render an SVG, drop it in as an element) or downstream (post-process the output).
- **Not a spreadsheet.** No formulas that reference other cells. Cell content is computed at fill time from row data + optional aggregate functions — closer to a printed statement than to Excel.
- **Not a WYSIWYG designer.** The planned form-based builder (Sub-project B) will let you compose JSON templates via forms, but the underlying model is still bands + elements at absolute positions — not free-hand drag-and-drop.
- **Not a print driver.** Rendering ends at HTML or JSON. Printing means the user's browser hitting Print, or the JSON stream being fed to another rendering backend (PDF, direct thermal printer, etc.) that this project doesn't ship.

## The two components

```
edgecase123/report-writer              (PHP library — writer/)
  └── The pipeline itself. No framework. No HTTP. No database.
      Takes params, returns a rendered string. Composer-installable.

writer-app                          (Slim 4 host app — writer-app/)
  └── Wraps the library in an HTTP layer, wires it to a SQLite database
      of sample coffee-shop data, serves the reports at /api/reports/*.

reporting-viewer                    (Vue 3 + TypeScript — frontend/)
  └── On-screen viewer + report-builder UI. Talks to writer-app via /api/*.
```

The library is the product; the host app + viewer exist to demonstrate it end-to-end and to be the shape a real deployment would take.

---

## Source files

| File | Role |
|---|---|
| `writer/src/ReportingPipeline.php` | The pipeline's public entry point — takes a filler + params, returns a `ReportStream` |
| `writer/src/Interfaces/ReportFillerInterface.php` | The one-method boundary between "params" and "rendered report" |
| `writer/src/Interfaces/ReportDataSourceInterface.php` | The one-method boundary between "runtime data" and "report content" |

## Related docs

- [Architecture](architecture.md) — the pipeline diagram and how the pieces fit
- [Glossary](glossary.md) — every term used above, precisely defined
- [Concepts → What is a report?](../03-concepts/what-is-a-report.md) — reporting fundamentals for someone new to the domain
