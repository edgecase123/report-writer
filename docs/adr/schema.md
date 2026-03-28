# Fill-to-Layout Contract

**Status:** Draft  
**Version:** 0.1  
**Owners:** Reporting

---

## 1. Purpose

This document defines the JSON contract emitted by the **fill stage** and consumed by the **layout stage** in the reporting pipeline.

The contract ensures:

- Fill resolves business data and expressions into concrete instances
- Layout performs sizing, splitting, pagination, and positioning
- Layout does **not** re-query business data
- Renderers consume layout output without needing report-definition semantics

---

## 2. Stage Boundaries

### 2.1 Fill Stage Responsibilities

Fill **must**:

- Evaluate all expressions (fields, parameters, variables)
- Resolve all content values (text, images, etc.)
- Emit concrete **band instances** in logical order
- Emit **element instances** with resolved content
- Expand subreports into **subreport instances**
- Provide initial geometry and layout directives

### 2.2 Layout Stage Responsibilities

Layout **must**:

- Compute effective element and band heights
- Apply stretch and split rules
- Perform pagination
- Assign final absolute positions
- Flatten subreport content into the page flow

### 2.3 Non-Goals for Layout

Layout **must not**:

- Query business data
- Evaluate expressions
- Traverse the original report definition
- Infer missing structure not present in this contract

---

## 3. Design Principles

- **Data-resolved:** All content is pre-evaluated by fill
- **Layout-ready:** Layout has everything required for pagination
- **Renderer-agnostic:** Output supports multiple renderers
- **Traceable:** Every instance maps to a template origin
- **Subreport-capable:** Nested report content is supported from the start
- **Deterministic:** Layout results depend only on this contract

---

## 4. Top-Level Structure

A filled report document contains:

- Report metadata
- Root band instances
- Subreport instances
- Optional diagnostics

### Example

```json
{
  "schema_version": "1.0",
  "report_instance_id": "rep_001",
  "report_definition_id": "invoice_report",
  "band_instances": [],
  "subreport_instances": [],
  "metadata": {}
}
```

---

## 5. FilledReport

Represents the complete output of the fill stage.

### Fields

| Field | Type | Required | Description |
|---|---|---:|---|
| schema_version | string | yes | Contract version |
| report_instance_id | string | yes | Unique report instance ID |
| report_definition_id | string | yes | Template report ID |
| band_instances | BandInstance[] | yes | Root-level band instances |
| subreport_instances | SubreportInstance[] | no | Nested report instances |
| metadata | object | no | Optional debug/trace info |

---

## 6. BandInstance

Represents one emitted instance of a report band.

### Fields

| Field | Type | Required | Description |
|---|---|---:|---|
| instance_id | string | yes | Unique instance ID |
| band_id | string | yes | Template band ID |
| band_type | BandType | yes | Type of band |
| parent_container_type | ContainerType | yes | root or subreport |
| parent_container_id | string | no | Subreport reference if applicable |
| elements | ElementInstance[] | yes | Elements in this band |
| x | number | yes | Local x origin |
| y | number | yes | Local y origin |
| width | number | yes | Intended width |
| height | number | yes | Initial/min height |
| can_grow | boolean | yes | Whether band may grow |
| split_behavior | SplitBehavior | yes | Splitting rule |

### Notes

- Bands are emitted in logical order
- Layout may change height and page placement
- Content must not change after fill

---

## 7. ElementInstance

Represents one rendered element inside a band.

### Common Fields

| Field | Type | Required | Description |
|---|---|---:|---|
| instance_id | string | yes | Unique ID |
| element_id | string | yes | Template element ID |
| kind | ElementKind | yes | Element type |
| x | number | yes | Local x position |
| y | number | yes | Local y position |
| width | number | yes | Width |
| height | number | yes | Initial height |
| content | object | yes | Content payload |
| can_grow | boolean | yes | May stretch vertically |
| can_shrink | boolean | yes | May shrink |

---

## 8. Content Types

### 8.1 TextContent

```json
{
  "content_type": "text",
  "value": "Acme Corp"
}
```

| Field | Type | Required | Description |
|---|---|---:|---|
| content_type | enum | yes | text |
| value | string | yes | Final text |

---

### 8.2 ImageContent

```json
{
  "content_type": "image",
  "src": "url-or-id"
}
```

| Field | Type | Required | Description |
|---|---|---:|---|
| content_type | enum | yes | image |
| src | string | yes | Image source reference |

---

### 8.3 SubreportContent

Represents a container for another report instance.

```json
{
  "content_type": "subreport",
  "subreport_instance_id": "sub_001",
  "stretch_with_overflow": true,
  "can_split_across_pages": true
}
```

| Field | Type | Required | Description |
|---|---|---:|---|
| content_type | enum | yes | subreport |
| subreport_instance_id | string | yes | Referenced subreport |
| stretch_with_overflow | boolean | yes | Grows to fit content |
| can_split_across_pages | boolean | yes | Allows page continuation |

---

## 9. SubreportInstance

Represents a fully filled nested report.

### Fields

| Field | Type | Required | Description |
|---|---|---:|---|
| subreport_instance_id | string | yes | Unique ID |
| report_definition_id | string | yes | Template ID |
| band_instances | BandInstance[] | yes | Nested band instances |
| parameters | object | no | Resolved parameters |

---

## 10. Enums

### BandType

- report_header
- page_header
- detail
- group_header
- group_footer
- page_footer
- summary

### ElementKind

- text
- image
- line
- rectangle
- subreport

### SplitBehavior

- forbidden
- allowed
- forced

### ContainerType

- root
- subreport

---

## 11. Geometry Semantics

- Coordinates are in report units
- Positions are relative to parent band
- Fill emits **intended geometry**
- Layout computes **final absolute positions**
- Heights are minimum unless fixed

---

## 12. Pagination Semantics

- Layout operates on a **single unified flow**
- Subreports do not paginate independently
- Subreport content is merged into parent flow
- Non-splittable bands must move entirely to next page
- Splittable bands may produce multiple fragments

---

## 13. Identity and Traceability

- Every instance must have a unique `instance_id`
- Every instance must reference its template origin
- Parent-child relationships must be explicit
- No implicit hierarchy via ordering

---

## 14. Validation Rules

- All `subreport_instance_id` values must resolve
- All elements must belong to exactly one band
- Root `band_instances` must not include subreport-owned bands
- Subreport bands must declare `parent_container_type = subreport`
- `content_type = subreport` requires valid reference

---

## 15. Minimal Example

```json
{
  "schema_version": "1.0",
  "report_instance_id": "r1",
  "report_definition_id": "simple",
  "band_instances": [
    {
      "instance_id": "b1",
      "band_id": "detail",
      "band_type": "detail",
      "parent_container_type": "root",
      "elements": [
        {
          "instance_id": "e1",
          "element_id": "name",
          "kind": "text",
          "x": 0,
          "y": 0,
          "width": 100,
          "height": 20,
          "can_grow": false,
          "can_shrink": false,
          "content": {
            "content_type": "text",
            "value": "Acme"
          }
        }
      ],
      "x": 0,
      "y": 0,
      "width": 500,
      "height": 20,
      "can_grow": false,
      "split_behavior": "allowed"
    }
  ]
}
```

---

## 16. Worked Example (Subreport)

Parent band includes subreport element:

```json
{
  "instance_id": "e_sub",
  "element_id": "line_items",
  "kind": "subreport",
  "x": 0,
  "y": 30,
  "width": 500,
  "height": 10,
  "can_grow": true,
  "can_shrink": false,
  "content": {
    "content_type": "subreport",
    "subreport_instance_id": "sub_1",
    "stretch_with_overflow": true,
    "can_split_across_pages": true
  }
}
```

Referenced subreport:

```json
{
  "subreport_instance_id": "sub_1",
  "report_definition_id": "line_items",
  "band_instances": [
    {
      "instance_id": "sb1",
      "band_id": "detail",
      "band_type": "detail",
      "parent_container_type": "subreport",
      "parent_container_id": "sub_1",
      "elements": [],
      "x": 0,
      "y": 0,
      "width": 500,
      "height": 20,
      "can_grow": false,
      "split_behavior": "allowed"
    }
  ]
}
```

---

## 17. Mapping to Code

This contract maps to:

- JSON Schema validation
- PHP DTO/value objects
- Fixture JSON test files
- Layout engine input structures

---

## 18. Open Questions

- Should element-level splitting be supported independently?
- Should horizontal overflow rules be included?
- Do we need explicit z-index layering?

---

# Summary

This document is the **source of truth for meaning and behavior**.

- JSON Schema → structure validation
- This document → semantics and rules
- Fixtures → behavioral correctness  