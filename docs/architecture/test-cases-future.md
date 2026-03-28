Yes. Here are **2 canonical layout test cases** that will lock down the most important v1 behavior.

They are chosen to validate exactly the rules you’ve been steering toward:

* layout owns pagination, not fill
* splitting is deterministic
* subreports behave as nested content containers
* parent/child ownership survives pagination
* subreport content is flattened into one unified pagination flow rather than running a separate page engine

---

# Canonical Test Case 1: Simple band split across pages

## Purpose

This proves the core v1 engine works at all.

It validates:

* fit test
* page break decision
* legal band split
* continuation fragment creation
* deterministic retry/placement
* absolute placement on both pages

If this test is unstable, everything else will be unstable too.

---

## Scenario

A report has:

* page height available for body content: **100**
* one detail band instance
* the band contains:

    * a fixed label block at top
    * a stretchable text block below it
* resolved total band height becomes **140**
* band is splittable
* text is splittable by line

---

## Input assumptions

### Page

```json
{
  "page_content_height": 100
}
```

### Pending flow

```json
[
  {
    "band_instance_id": "detail_1",
    "band_type": "detail",
    "can_split": true,
    "keep_together": false,
    "elements": [
      {
        "element_instance_id": "label_1",
        "kind": "text",
        "x": 0,
        "y": 0,
        "width": 200,
        "height": 20,
        "stretch": false,
        "content": "Notes:"
      },
      {
        "element_instance_id": "body_1",
        "kind": "text",
        "x": 0,
        "y": 20,
        "width": 200,
        "height": 20,
        "stretch": true,
        "can_split": true,
        "measured_height": 120,
        "content": "Long wrapped paragraph..."
      }
    ]
  }
]
```

---

## Expected layout reasoning

### Step 1

Resolve element heights:

* `label_1` = 20
* `body_1` = 120

### Step 2

Resolve band natural height:

* top label occupies `y=0..20`
* text occupies `y=20..140`
* band natural height = **140**

### Step 3

Fit test against remaining page height 100:

* required = 140
* available = 100
* result = does not fit

### Step 4

Band is splittable, so split is attempted.

Preferred split point:

* fixed label stays with first fragment
* text splits by lines
* first fragment consumes exactly the space available

So:

* fragment 1 height = 100
* fragment 2 height = 40

### Step 5

Place fragment 1 on page 1.

### Step 6

Start page 2 and place fragment 2 at top of body area.

---

## Expected output shape

```json
{
  "pages": [
    {
      "page_index": 1,
      "bands": [
        {
          "fragment_id": "detail_1#1",
          "source_band_instance_id": "detail_1",
          "x": 0,
          "y": 0,
          "width": 200,
          "height": 100,
          "continues_on_next_page": true
        }
      ]
    },
    {
      "page_index": 2,
      "bands": [
        {
          "fragment_id": "detail_1#2",
          "source_band_instance_id": "detail_1",
          "x": 0,
          "y": 0,
          "width": 200,
          "height": 40,
          "is_continuation": true
        }
      ]
    }
  ]
}
```

---

## Assertions

Your test should assert:

1. Page count is **2**
2. First fragment height is **100**
3. Second fragment height is **40**
4. Fragment order is stable: `detail_1#1` then `detail_1#2`
5. `detail_1#2` preserves source identity back to `detail_1`
6. Already-placed page 1 content is not recomputed after page 2 placement
7. Absolute coordinates are page-relative, not report-global

---

## Why this is canonical

Because this is the smallest test that proves:

* measurement
* fit
* split
* continuation
* placement

without involving subreports yet.

---

# Canonical Test Case 2: Stretching subreport container split across pages

## Purpose

This is the most important architectural test.

It validates the exact subreport behavior you want:

* subreport exists as an element contract
* it expands into child filled content
* it acts like a stretching container
* child content is paginated by the same engine
* split occurs at child band boundaries
* ownership links survive across fragments

---

## Scenario

A parent detail band contains:

* customer name text at top
* a subreport placeholder below it

The subreport contains three child detail bands representing line items.

Available height on the current page is **100**.

Parent content:

* customer name block = 20 high
* subreport begins at y = 20

Subreport child bands:

* line 1 = 30
* line 2 = 30
* line 3 = 30

Total parent height becomes:

* 20 + 90 = **110**

So it does not fit on the current page.

The subreport is configured as:

* `stretch_with_overflow = true`
* `can_split_across_pages = true`

This means the parent band should split around the child flow, and pagination is handled uniformly by the main layout engine.

---

## Input assumptions

```json
{
  "band_instance_id": "order_1",
  "band_type": "detail",
  "can_split": true,
  "elements": [
    {
      "element_instance_id": "customer_name_1",
      "kind": "text",
      "x": 0,
      "y": 0,
      "width": 300,
      "height": 20,
      "stretch": false,
      "content": "Customer: Acme"
    },
    {
      "element_instance_id": "lines_subreport_1",
      "kind": "subreport",
      "x": 0,
      "y": 20,
      "width": 300,
      "height": 1,
      "content": {
        "content_type": "subreport",
        "subreport_instance_id": "sub_1"
      },
      "stretch_with_overflow": true,
      "can_split_across_pages": true
    }
  ]
}
```

```json
{
  "subreport_instances": [
    {
      "subreport_instance_id": "sub_1",
      "band_instances": [
        {
          "band_instance_id": "line_1",
          "parent_element_instance_id": "lines_subreport_1",
          "container_type": "subreport",
          "resolved_height": 30
        },
        {
          "band_instance_id": "line_2",
          "parent_element_instance_id": "lines_subreport_1",
          "container_type": "subreport",
          "resolved_height": 30
        },
        {
          "band_instance_id": "line_3",
          "parent_element_instance_id": "lines_subreport_1",
          "container_type": "subreport",
          "resolved_height": 30
        }
      ]
    }
  ]
}
```

---

## Expected layout reasoning

### Step 1

Resolve parent fixed content:

* customer name = 20

### Step 2

Resolve subreport contribution from child flow:

* line 1 = 30
* line 2 = 30
* line 3 = 30
* subreport natural contribution = 90

### Step 3

Resolve parent natural height:

* 20 + 90 = 110

### Step 4

Fit test:

* required = 110
* available = 100
* does not fit

### Step 5

Band is splittable.
Subreport is splittable.
Preferred split boundary is at child band boundaries, not inside a child line item.

Available remaining height after customer block:

* 100 - 20 = 80

Subreport child flow that can fit:

* line 1 (30) fits
* line 2 (30) fits
* line 3 (30) would exceed remaining 80

So page 1 contains:

* parent header text (20)
* subreport child line 1 (30)
* subreport child line 2 (30)

Total page 1 fragment height = **80**

Continuation child flow:

* line 3 only

Page 2 then contains:

* continuation of parent band/subreport
* line 3 at top of continuation area

---

## Expected output shape

```json
{
  "pages": [
    {
      "page_index": 1,
      "bands": [
        {
          "fragment_id": "order_1#1",
          "source_band_instance_id": "order_1",
          "height": 80,
          "continues_on_next_page": true,
          "elements": [
            {
              "element_fragment_id": "customer_name_1#1",
              "source_element_instance_id": "customer_name_1",
              "x": 0,
              "y": 0,
              "width": 300,
              "height": 20
            },
            {
              "element_fragment_id": "lines_subreport_1#1",
              "source_element_instance_id": "lines_subreport_1",
              "x": 0,
              "y": 20,
              "width": 300,
              "height": 60
            }
          ],
          "child_band_fragments": [
            {
              "fragment_id": "line_1#1",
              "source_band_instance_id": "line_1",
              "parent_element_instance_id": "lines_subreport_1",
              "container_type": "subreport",
              "x": 0,
              "y": 20,
              "height": 30
            },
            {
              "fragment_id": "line_2#1",
              "source_band_instance_id": "line_2",
              "parent_element_instance_id": "lines_subreport_1",
              "container_type": "subreport",
              "x": 0,
              "y": 50,
              "height": 30
            }
          ]
        }
      ]
    },
    {
      "page_index": 2,
      "bands": [
        {
          "fragment_id": "order_1#2",
          "source_band_instance_id": "order_1",
          "is_continuation": true,
          "height": 30,
          "elements": [
            {
              "element_fragment_id": "lines_subreport_1#2",
              "source_element_instance_id": "lines_subreport_1",
              "x": 0,
              "y": 0,
              "width": 300,
              "height": 30
            }
          ],
          "child_band_fragments": [
            {
              "fragment_id": "line_3#1",
              "source_band_instance_id": "line_3",
              "parent_element_instance_id": "lines_subreport_1",
              "container_type": "subreport",
              "x": 0,
              "y": 0,
              "height": 30
            }
          ]
        }
      ]
    }
  ]
}
```

---

## Assertions

Your test should assert:

1. Page count is **2**
2. Parent band splits into exactly **2 fragments**
3. Page 1 contains customer block + line 1 + line 2
4. Page 2 contains only continuation with line 3
5. Split point occurs **between child subreport bands**, not inside one
6. `parent_element_instance_id = lines_subreport_1` is preserved on all child fragments
7. `container_type = subreport` is preserved
8. There is **no independent subreport page numbering**
9. Parent and child content share one page sequence, proving unified pagination

---

# Why these 2 are the right first canonical tests

Together they prove the two most important parts of the system:

## Test 1

Proves the generic pagination kernel:

* fit
* split
* continue
* place

## Test 2

Proves the architectural commitment:

* subreport is not a leaf
* child flow participates in the same paginator
* split preference is at child band boundaries
* ownership survives fragmentation

---