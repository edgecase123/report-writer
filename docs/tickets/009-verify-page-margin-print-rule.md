# TICKET-009: Verify or add `@page { margin: 0 }` for print output

**Priority:** Low
**Source:** frontend-designer audit (2026-08-22) — 🟡 Print behavior audit
**Scope:** `writer/src/Renderer/HtmlRenderer.php` head block, potentially `frontend/src/App.vue` global styles

## Problem

The frontend-designer role documentation describes `@page { margin: 0 }` as a required print rule (removes browser-added print margins so the report's own 20pt page margins are the only ones applied). No `@page` rule appears in the frontend CSS. Whether one appears in the server-emitted `<style>` block from `HtmlRenderer::head()` needs verification.

Without `@page { margin: 0 }`, printed output picks up the browser's default margins (~0.5" all around), which will:

- Push the report content inward from where the on-screen preview shows it
- Break the "print output matches on-screen preview" property
- Cause single-page reports to sometimes overflow to a second page depending on browser + printer

## Proposed fix

Two-step:

1. **Verify** whether `HtmlRenderer::head()` at `writer/src/Renderer/HtmlRenderer.php:147-169` includes an `@page { margin: 0 }` rule.
2. **If missing:** add it to the head block, so the rule ships in every rendered report.

```php
// In HtmlRenderer::head() — the <style> block should include:
@page { margin: 0; }
```

If it's present on the server side, the frontend viewer inherits it via the extracted `<style>` blocks. No frontend change needed.

If we want defence-in-depth, add the same rule to `App.vue`'s `@media print` block:

```css
@media print {
    @page { margin: 0; }
    /* ...existing rules... */
}
```

## Acceptance criteria

- [ ] Server-side head block emits `@page { margin: 0 }` (or its equivalent for the configured page size)
- [ ] Manual test: print preview of any sample report shows content flush to page edges (respecting only the report's own 20pt margins from `PageConfig`)

## Notes

- Currently `HtmlRenderer::head()` DOES include `@page { margin: 0 }` at line 161 — [verify]. If confirmed, this ticket becomes doc-only (add a note in `docs/04-pipeline/04-render.md` when that page is written).
