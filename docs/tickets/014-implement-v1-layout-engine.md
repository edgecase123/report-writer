# TICKET-014: Implement v1 layout engine per `docs/architecture/` specs

**Priority:** Epic — long-term architectural
**Source:** `docs/architecture/*.md` (specs mirrored from upstream)
**Status:** Backlog
**Scope:** Complete rewrite of `writer/src/Layout/`, potentially `writer/src/Instance/*` additions, updates to `writer/src/Renderer/*` to consume the new stream shape

## Problem

The current `writer/src/Layout/LayoutService.php` implements a proper subset of the v1 target spec. Gap analysis (from `docs/architecture/README.md`):

**Shipped:** single-pass pagination, fit → place, single-element splittable text bands, deterministic output, Fill/Layout separation, `ElementExceedsPageException`.

**Partial (functionally present, output shape doesn't match spec):**
- Subreports (`Flattener` inlines them BEFORE layout, discards `parent_element_instance_id` / `container_type` linkage)
- Continuation fragments (splits produce two separate `PositionedElement`s with no `fragment_id` / `continues_on_next_page` linkage)

**Not shipped:**
- Keep-together / keep-with-next / group-keep-with-first-detail rules
- Forced page breaks (`page_break_before` / `page_break_after` flags)
- Page headers and footers (reserved space at top/bottom of each page)
- Stretchable elements with content-measured heights (all element heights fixed at construction)
- Non-text element kinds beyond `TextContent` + `SubreportContent` (no image/shape/line)
- Emergency overflow policy for oversized non-splittable content
- Layout diagnostics with traceability IDs beyond `ElementExceedsPageException`

## Deliverable

- New `LayoutService` implementing the full spec at `docs/architecture/layout-algorithm-spec.md`
- New output stream shape with fragment identity + parent linkage per `docs/architecture/fill-to-layout-schema.md`
- Renderer updates to consume the new shape
- The two canonical tests at `docs/architecture/test-cases-future.md` become writable and pass

## Blockers

- Design decisions on the new output shape (fragment IDs, continuation links, container child fragments)
- May coordinate with Sub-project A's `HtmlRenderer` output — if the new stream shape lands mid-A, renderer changes cascade

## Notes

- Explicitly out of scope for Sub-project A. A ships against the current shipped subset.
- The spec is normative (MUST/SHOULD language). Deviations from the spec should be justified in an ADR before landing.
- Not a small effort — plausibly a multi-month project of its own. Requires its own brainstorming pass first.
