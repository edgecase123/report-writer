# Minimal Layout Algorithm Spec

## 1. Scope of layout

Layout accepts only **filled, data-resolved instances**.

Layout does **not**:

* evaluate business expressions
* fetch data
* decide which records exist
* build subreport data

Layout does:

* measure elements
* resolve stretch
* resolve final band heights
* split splittable content
* paginate
* compute absolute boxes

That separation is important because the schema direction is that fill emits resolved band instances and layout handles pagination uniformly, including subreport-expanded content.

---

## 2. Inputs to the algorithm

Input is one filled report instance containing:

* ordered root `band_instances`
* optional `subreport_instances`
* resolved element content
* design-time geometry copied into instances
* split/stretch/pagination directives

Subreports should already be represented as nested report content referenced from the parent placeholder, not as something layout has to fill itself.

---

## 3. Core invariants

These rules should never change:

1. **Layout is single-pass over a normalized flow list**

    * No hidden second data pass.
    * Re-layout is allowed only when a split creates continuation fragments.

2. **Every emitted layout object has one owner**

    * page
    * band fragment
    * element fragment

3. **Band order is stable**

    * layout never reorders siblings

4. **Parent coordinates are always resolved before child absolute coordinates**

5. **A page break only happens at explicit decision points**

    * before placing a band
    * while splitting a band
    * after finishing a band if a rule requires next-page continuation

6. **Subreports participate as containers in the same pagination system**

    * not as a separate page engine inside the parent

---

## 4. Normalized processing model

Before pagination, normalize the input into a flow tree:

* root report instance

    * root band instances in order
    * subreport placeholders refer to child report instances
    * child report instances expose their own band instances

Layout should then treat a subreport as:

* an element at definition time
* a **container of child flow content** at layout time

This matches the schema idea that a subreport is really a viewport/container for another filled report instance.

---

## 5. Page state

Maintain a tiny mutable page state:

* `current_page_index`
* `page_width`
* `page_height`
* `content_top`
* `content_bottom`
* `cursor_y`
* `remaining_height`

Derived:

* `remaining_height = content_bottom - cursor_y`

This state is the only source of truth for placement.

---

## 6. High-level algorithm

Process in this order:

### Step 1: Start first page

* initialize page state
* place page header if applicable
* set `cursor_y` to first body position

### Step 2: Iterate root band instances in order

For each band instance:

* resolve band layout
* if it fits, place it
* if not, split or defer according to rules
* update cursor
* emit page break when required

### Step 3: Finish page

When no more content fits or a forced break occurs:

* place page footer if applicable
* finalize page output
* start next page

### Step 4: Finish report

* emit summary/final sections if they exist
* finalize last page

---

## 7. Band layout procedure

For each band instance, do this in exactly this order.

### 7.1 Resolve child element intrinsic sizes

For each element:

* fixed elements keep design width/height
* stretchable text measures natural height from width/content
* images/shapes use known intrinsic or fixed box rules
* subreport elements resolve as container placeholders, not leaf boxes

### 7.2 Resolve element final heights

Apply rules:

* fixed height stays fixed unless explicit stretch allowed
* stretchable elements can grow
* no element may shrink below minimum height
* subreport element height is either:

    * fixed, or
    * content-driven if `stretch_with_overflow=true`

### 7.3 Resolve band natural height

Band natural height is:

* max bottom edge of all non-floating children
* including stretch growth
* including resolved subreport container contribution

### 7.4 Apply band min/max constraints

If any exist.

### 7.5 Determine splittability

Band is split-capable only if:

* band rule allows splitting
* at least one child can split or continue
* orphan/widow rules do not forbid current split point

---

## 8. Fit test

Before placement, compute:

* `required_height = resolved band height`
* `available_height = remaining_height`

Then:

### Case A: Fits fully

If `required_height <= available_height`

* place band on current page

### Case B: Does not fit, band cannot split

* start a new page
* retry once on fresh page
* if still too large for an empty page:

    * either hard-fail with diagnostic
    * or force split only if band type explicitly supports overflow emergency mode

### Case C: Does not fit, band can split

* perform band split against `available_height`

This retry-on-new-page rule keeps behavior predictable.

---

## 9. Band splitting rules

A split must create:

* `head_fragment`
* optional `tail_fragment`

Rules:

1. Preserve original band identity plus fragment identity
2. Keep sibling order inside each fragment
3. Only split at legal split boundaries
4. Child fragments inherit style and traceability
5. Tail fragment is re-queued immediately after current fragment

### Legal split boundaries

A split boundary may occur only:

* between stacked child blocks
* inside a text element if text splitting is allowed
* inside a subreport container if child flow can continue across pages
* never through a non-splittable atomic element

### Split preference

Prefer:

1. split between child blocks
2. split subreport child flow at child band boundary
3. split text lines
4. emergency split only if explicitly enabled

---

## 10. Subreport layout rules

This is the minimal clean rule set.

### 10.1 Subreport is a container

At layout time, a subreport element expands to child report flow. It is not just a leaf rectangle.

### 10.2 Single pagination owner

The parent layout engine paginates both parent and child content uniformly. Do not let a nested subreport run an independent page model.

### 10.3 Container height resolution

If fixed:

* use fixed height

If stretch:

* resolve from child content consumed in current fragment/page context

### 10.4 Splitting

If `can_split_across_pages=true`:

* split at child band boundaries first
* child tail continues on next page
* parent band fragment also continues as needed

If false:

* subreport behaves atomically for pagination

### 10.5 Ownership links

Every child band inside subreport should preserve parent linkage such as:

* `parent_element_instance_id`
* `parent_band_instance_id`
* `container_type`

This makes debugging and rendering much easier.

---

## 11. Absolute placement rules

Once a band fragment is accepted onto a page:

### 11.1 Band box

Compute:

* `page_x`
* `page_y = cursor_y`
* `width`
* `height`

### 11.2 Element boxes

For each child element fragment:

* `abs_x = band.page_x + element.rel_x`
* `abs_y = band.page_y + element.rel_y`
* width/height from resolved fragment box

### 11.3 Container children

If element is a subreport container:

* child band fragments are placed relative to the container origin
* then converted to page-absolute coordinates

Renderer should receive only absolute output.

---

## 12. Requeue / continuation rules

When a band or subreport splits:

* emit current fragment now
* create continuation fragment
* continuation goes next in the pending queue
* do not recompute earlier placed content
* do not reorder around the continuation unless the report definition explicitly supports deferred sections

This makes pagination deterministic.

---

## 13. Page break rules

Support only these minimal break causes:

1. **Natural overflow**

    * next band does not fit

2. **Forced break before band**

    * explicit page break flag

3. **Forced break after band**

    * explicit page break after flag

4. **Group/page policy**

    * e.g. keep header with first detail if required

Avoid introducing more break modes until needed.

---

## 14. Keep-together rules

Minimal version:

### Band keep-together

If enabled:

* band either fits whole or moves to next page
* unless larger than empty page and emergency split policy allows exception

### Header-with-next

If enabled:

* header cannot be last item on page without required follower

### Group keep-with-first-detail

If enabled:

* test combined height before placement

These rules should be checked before ordinary fit/split.

---

## 15. Recommended deterministic decision order

For each pending band instance, use this exact decision order:

1. Resolve intrinsic child sizes
2. Resolve band natural height
3. Apply keep-together / keep-with-next checks
4. Test fit on current page
5. If fits, place
6. If not fit and forced unsplittable, new page then retry
7. If splittable, split at best legal boundary
8. Place head fragment
9. Start new page if tail remains
10. Requeue tail immediately
11. Continue

That order will keep implementation understandable.

---

## 16. Minimal pseudocode

```text
layout(report):
  pages = []
  queue = normalize_root_flow(report)

  start_new_page()

  while queue not empty:
    item = queue.pop_front()

    resolved = resolve_band(item, current_page)

    if violates_keep_rules(resolved, current_page):
      finish_page()
      start_new_page()
      queue.push_front(item)
      continue

    if fits(resolved, current_page):
      place_band(resolved, current_page)
      continue

    if not resolved.can_split:
      if current_page_has_content():
        finish_page()
        start_new_page()
        queue.push_front(item)
        continue
      else:
        error_or_emergency_overflow(item)
        continue

    split = split_band(resolved, current_page.remaining_height)

    if split.head is not null:
      place_band(split.head, current_page)

    if split.tail is not null:
      finish_page()
      start_new_page()
      queue.push_front(split.tail)

  finish_last_page()
  return pages
```

---

## 17. What to keep out of v1

To keep it clean, do **not** include these in the first implementation:

* independent subreport pagination engine
* backward reflow of already placed pages
* floating re-packing around variable-height neighbors
* column balancing
* advanced widow/orphan typography
* arbitrary overlap resolution
* dynamic z-order conflict solving

Those can all come later.

---

## 18. Minimal output of layout stage

Layout output should contain:

* pages
* page boxes
* band fragments with page membership
* element fragments with absolute coordinates
* continuation links
* source trace IDs back to band/element/template/subreport instance

That keeps the HTML renderer simple: it should only paint pages and absolutely positioned boxes.

---

## 19. The one-sentence rule for the implementation

**Fill decides what exists; layout decides where it goes and how it splits.**

That matches the contract direction in your schema notes, including the recommendation that subreport content be represented explicitly and paginated by one unified layout engine.

If you want, I can turn this next into a **normative v1 spec with MUST/SHOULD/MAY language**.
