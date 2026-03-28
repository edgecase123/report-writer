## Layout Algorithm Specification v1

### Status

Draft normative specification for the **layout/pagination stage** of the reporting pipeline.

### Purpose

This specification defines the required behavior of the layout stage so that implementations remain:

* predictable
* deterministic
* traceable
* easy to test
* independent from business-data evaluation

This spec follows the contract direction that fill emits resolved instances and layout performs pagination, splitting, and absolute placement, including for subreport content.

---

# 1. Scope

The layout stage **MUST** consume only filled, data-resolved report content.

The layout stage **MUST NOT**:

* query business data
* evaluate report expressions
* decide which records exist
* execute subreport data fetching
* alter logical report ordering

The layout stage **MUST**:

* measure intrinsic content where needed
* resolve element growth/stretch
* resolve final band heights
* split eligible content
* paginate content into pages
* compute final absolute boxes for rendering

---

# 2. Processing model

The layout engine **MUST** operate as a **single pagination owner** for the full report flow.

This means:

* root report content and subreport content **MUST** be paginated by the same layout engine
* subreports **MUST NOT** own an independent page model in v1
* subreport content **MUST** participate in the same page-breaking rules as parent content

This matches the recommended architecture where subreports expand into nested content and are paginated uniformly by layout.

---

# 3. Input assumptions

The input to layout **MUST** already contain:

* ordered `band_instances`
* resolved element content
* design-time geometry copied into instances
* split/stretch directives
* subreport references and/or child filled report instances
* traceability identifiers

If subreports are present, they **MUST** be represented as explicit child content, not as something layout has to fill itself.

---

# 4. Core invariants

The implementation **MUST** preserve the following invariants.

## 4.1 Stable ordering

Sibling band order **MUST NOT** change during layout.

## 4.2 Single ownership

Every emitted layout object **MUST** have exactly one owner:

* page
* band fragment
* element fragment
* container fragment

## 4.3 No hidden data pass

Layout **MUST NOT** perform a second business-data pass.

## 4.4 Parent-first resolution

A parent container’s box **MUST** be resolved before the absolute boxes of its children are finalized.

## 4.5 Explicit page-break decisions

Page breaks **MUST** occur only at defined decision points:

* before placing a pending band
* while splitting a band
* after placing a band when a rule explicitly requires a new page

## 4.6 Deterministic retry behavior

If content is retried on a new page, the retry behavior **MUST** be deterministic and produce the same result for identical inputs.

---

# 5. Normalized flow model

Before pagination, the implementation **SHOULD** normalize the input into a flow tree or equivalent internal structure.

That normalized structure **SHOULD** represent:

* the root report flow
* ordered root band instances
* subreport container elements
* child report flows attached to subreport containers

At layout time, a subreport **MUST** be treated as a **container of child flow**, not merely as a leaf rectangle.

---

# 6. Page state

The layout engine **MUST** maintain page state sufficient to make placement decisions.

At minimum, page state **MUST** include:

* current page index
* page width
* page height
* content top
* content bottom
* current cursor y
* remaining height

The implementation **MUST** treat remaining height as derived from current cursor and page content bounds.

---

# 7. Required processing order

For each pending band instance, layout **MUST** apply the following steps in order:

1. resolve intrinsic child sizes
2. resolve child final heights
3. resolve natural band height
4. apply band constraints
5. apply keep rules
6. test fit on current page
7. either place, defer, or split
8. emit absolute placement
9. update cursor/page state

An implementation **MUST NOT** skip or reorder these steps in a way that changes semantics.

---

# 8. Element sizing rules

## 8.1 Fixed-size elements

A fixed-size element **MUST** retain its defined width and height unless its own rules explicitly allow stretch.

## 8.2 Stretchable text

A stretchable text element **MUST** be measured using its resolved content and available width.

Its final height **MUST** be based on measured wrapped content, subject to any minimum or maximum constraints.

## 8.3 Non-text atomic elements

Images, shapes, lines, and similar atomic elements **MUST** be treated as unsplittable unless explicitly declared splittable by type-specific rules.

## 8.4 Subreport elements

A subreport element **MUST** resolve as a container placeholder whose height is either:

* fixed by rule, or
* content-driven when stretch/overflow growth is enabled

---

# 9. Band height resolution

The natural height of a band **MUST** be computed from its resolved children.

At minimum, the natural band height **MUST** include:

* all child resolved heights
* child y offsets
* stretch growth
* subreport container contribution
* any required padding included by the model

If constraints exist, the implementation **MUST** apply them after natural height calculation.

---

# 10. Fit test

Before placement, the engine **MUST** compare:

* required height
* available remaining page height

## 10.1 Full fit

If the band fits fully, the engine **MUST** place it on the current page.

## 10.2 Does not fit, cannot split

If the band does not fit and cannot split:

* if the current page already contains placeable body content, the engine **MUST** start a new page and retry once
* if it still does not fit on an empty page, the engine **MUST** either:

    * raise a layout diagnostic/failure, or
    * apply an explicitly configured emergency overflow behavior

An implementation **MUST NOT** silently invent a split for a non-splittable band.

## 10.3 Does not fit, can split

If the band does not fit and may split, the engine **MUST** attempt a legal split.

---

# 11. Keep rules

The following keep behaviors are valid in v1.

## 11.1 Keep together

If a band is marked keep-together, it **MUST** be placed whole or moved to the next page, unless an explicit emergency exception policy applies.

## 11.2 Keep with next

If a band is marked keep-with-next, it **MUST NOT** be left at the bottom of a page without its required follower.

## 11.3 Group keep-with-first-detail

If a group header requires the first detail row with it, layout **MUST** test combined placement before placing the header.

Keep rules **MUST** be checked before ordinary split logic.

---

# 12. Band splitting

If a band is split, the layout engine **MUST** create fragments.

At minimum, split output **MUST** support:

* a head fragment placed on the current page
* a tail fragment queued for continuation

## 12.1 Fragment identity

Each fragment **MUST** preserve traceability to:

* original band instance
* original band definition
* fragment sequence or continuation identity

## 12.2 Stable child order

Children within fragments **MUST** preserve logical order.

## 12.3 Legal split boundaries

A split **MUST** occur only at legal split boundaries.

Legal boundaries are:

* between stacked child blocks
* at child container boundaries
* within text content when text splitting is explicitly allowed
* within subreport child flow when the subreport is allowed to continue across pages

A split **MUST NOT** cut through an atomic unsplittable element.

## 12.4 Split preference

When multiple legal split points exist, the engine **SHOULD** prefer them in this order:

1. between child blocks
2. at subreport child band boundaries
3. at text line boundaries
4. emergency split only if explicitly enabled

---

# 13. Continuation handling

When a split produces a tail fragment, the engine **MUST**:

* emit the head fragment immediately if placeable
* carry the tail fragment forward as the next pending continuation
* preserve ordering relative to surrounding content
* avoid reflowing already accepted prior content

The continuation fragment **SHOULD** be queued immediately after the current fragment in logical flow.

---

# 14. Subreport rules

## 14.1 Subreport is a container

A subreport **MUST** be treated as a container of child report content at layout time.

## 14.2 Parent-child linkage

Child content emitted within a subreport **MUST** retain ownership linkage sufficient to identify:

* parent element instance
* parent band instance when applicable
* container type or equivalent nesting identity

## 14.3 Height behavior

If a subreport container is fixed-height, layout **MUST** use that fixed height.

If it is stretch-driven, layout **MUST** resolve its contribution from child content consumed in the current fragment/page context.

## 14.4 Subreport splitting

If the subreport is allowed to split across pages, layout **MUST** prefer splitting at child band boundaries.

If subreport splitting is not allowed, the container **MUST** behave atomically for pagination.

## 14.5 Unified pagination

The layout engine **MUST NOT** let the subreport paginate independently from the parent engine in v1.

---

# 15. Absolute placement

Once a band or band fragment is accepted onto a page, the engine **MUST** compute absolute placement.

## 15.1 Band absolute box

Each placed band fragment **MUST** have:

* page index
* absolute x
* absolute y
* width
* height

## 15.2 Element absolute box

Each element fragment **MUST** have:

* page index
* absolute x
* absolute y
* width
* height

Element absolute coordinates **MUST** be derived from:

* parent band/container absolute origin
* child relative geometry

## 15.3 Container descendants

Descendants inside subreport containers **MUST** be placed relative to the container and then converted to page-absolute coordinates.

---

# 16. Page lifecycle

## 16.1 Page start

When starting a new page, the engine **MUST** initialize page state before body placement.

## 16.2 Headers and footers

If page headers or footers exist, the engine **MUST** apply them consistently according to report rules.

Their reserved space **MUST** be reflected in body content bounds.

## 16.3 Page finalization

When a page ends, the engine **MUST** finalize all objects assigned to that page before creating the next page.

---

# 17. Page-break causes

v1 supports only the following page-break causes:

1. content overflow
2. forced break before a band
3. forced break after a band
4. keep/group rules requiring deferral to next page

An implementation **SHOULD NOT** introduce additional implicit break causes in v1.

---

# 18. Error and diagnostic behavior

The engine **MUST** be able to report a diagnostic when content cannot be laid out legally.

This includes cases such as:

* unsplittable content larger than an empty page
* invalid ownership tree
* circular nesting
* impossible keep constraints
* malformed geometry

The engine **SHOULD** emit diagnostics with traceability identifiers.

---

# 19. Determinism requirement

For identical input and equivalent measurement rules, layout output **MUST** be deterministic.

Determinism includes:

* same page count
* same page membership
* same split boundaries
* same absolute coordinates
* same continuation structure

---

# 20. Minimal output contract

The layout stage output **MUST** contain enough information for a renderer to paint without further pagination logic.

At minimum, layout output **MUST** include:

* ordered pages
* page dimensions
* placed band fragments
* placed element fragments
* page membership
* absolute coordinates
* continuation/fragment linkage
* traceability back to original report/band/element/subreport identities

The renderer **MUST NOT** need to recalculate pagination.

---

# 21. Recommended implementation note

A conforming implementation will usually be simplest if it uses:

* a pending queue of bands/fragments
* a page state object
* a pure fit/split/place loop
* traceable fragment generation

A minimal control loop is:

```text
while pending items exist:
  resolve next item
  apply keep checks
  if fits:
    place
  else if splittable:
    split, place head, queue tail
  else:
    new page and retry
```

---

# 22. Non-goals for v1

The following are explicitly out of scope for v1:

* independent nested page engines
* backward reflow of already finalized pages
* advanced widow/orphan typography
* multi-column balancing
* dynamic overlap solving
* float-style re-packing
* arbitrary z-order conflict resolution

---

# 23. Governing principle

**Fill decides what exists. Layout decides where it goes and how it splits.**

That is the simplest rule for keeping the implementation clean and predictable, while staying aligned with the subreport-capable contract direction in your schema notes.

I can turn this next into a repo-ready `layout-algorithm-spec.md` file with a tighter RFC style.
