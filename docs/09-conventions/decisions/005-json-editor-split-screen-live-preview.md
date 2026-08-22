# ADR-005: Split-screen JSON editor with debounced live preview + CodeMirror 6

**Status:** Accepted
**Date:** 2026-08-22

## Context

Sub-project A includes a builder UI for JSON templates. Options considered:

- **Explicit "Preview" button** — click to submit JSON, render result
- **Debounced live preview** — auto-render on every debounced keystroke
- **Structured form builder** — add/remove/reorder bands and elements via forms; JSON generated under the hood

Editor library options: CodeMirror 6, Monaco, plain `<textarea>`.

## Decision

**Split-screen JSON editor with debounced (400 ms) auto-preview**, using **CodeMirror 6** with `@codemirror/lang-json` + `@codemirror/lint` + `@codemirror/theme-one-dark`.

**Preview renders into an `<iframe srcdoc>`** for style isolation.

Structured form builder is Sub-project B (see [ADR-010](010-sub-project-decomposition-a-then-b.md)).

## Rationale

**Split-screen live preview:**

- The "live" feel is the point of a builder — instant feedback on schema changes closes the tight edit-verify-edit loop
- Explicit-button-only feels sluggish and adds a click per iteration
- Structured form builder is a legitimate future direction but a whole project of its own — bundling it into A doubles the scope

**Debounce 400 ms:**

- Long enough to avoid POSTing on every keystroke
- Short enough that the "live" quality still works
- Combined with server-side `/api/preview` rate-limiting, prevents runaway request storms

**CodeMirror 6:**

- Well-maintained, tree-shakable, ~200 KB with the JSON language pack
- Native support for syntax highlighting, bracket matching, code folding, gutter lint markers
- Modular extension API — future additions (schema-driven autocomplete, per-position hover tooltips) fit cleanly
- Monaco is 5× heavier (~1.5 MB) with no meaningful benefit for a JSON editor
- Plain `<textarea>` loses the "feels live" quality that justifies the split-screen at all

**Iframe preview:**

- Server-generated HTML is a full `<html>/<head>/<style>/<body>` document
- Injecting via `v-html` leaks the preview's `<style>` into the builder page's CSS scope
- Iframe isolates the preview's `@media print` rules and absolute-positioned pages from the builder's flexbox layout
- Preview errors (server 400 for malformed JSON) render as plain HTML from the server; iframe still displays them fine
- Trade-off: iframe is heavier per swap, but acceptable at 400 ms debounce cadence

## Rejected alternatives

Covered above. Also considered: **CodeMirror 5** (previous generation, less modular, deprecated for new projects).

## Consequences

- `codemirror` + `@codemirror/lang-json` + `@codemirror/lint` + `@codemirror/theme-one-dark` become runtime dependencies of the frontend
- The builder UI's error surface (parse errors, server 400s, network failures) has a specific policy: last-good render stays visible dimmed while the JSON is invalid; error banner sits above the preview
- If a stakeholder later wants the structured form builder, that's Sub-project B and inherits nothing from this decision except the underlying preview endpoint
