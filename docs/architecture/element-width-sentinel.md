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
