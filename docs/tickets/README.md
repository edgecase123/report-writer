# Tickets

Local ticket store — GitHub-issue-shaped so each file can be promoted to `edgecase123/report-writer` via `gh issue create --repo edgecase123/report-writer --title "..." --body-file <file>` when appropriate.

**Origin:** all tickets in this batch surfaced during the 2026-08-22 audit pass — three local agents (`dry-solid-reviewer`, `security-scanner`, `frontend-designer`) run against the library + frontend, plus design decisions from the standalone-build brainstorming session.

## Ticket ledger

| # | Title | Priority | Source | Status |
|---|---|---|---|---|
| [001](001-aggregate-math-dry-consolidation.md) | Consolidate aggregate math (DefinitionFiller ↔ AggregateExpression) | High | dry-solid-reviewer | ✅ Closed (A1) |
| [002](002-delete-dead-code-definition-namespace.md) | Delete dead code: `Interfaces/DataProviderInterface` + entire `Definition/*` namespace | Medium | dry-solid-reviewer | ✅ Closed (A1) |
| [003](003-reportbuilder-element-loop-refactor.md) | Refactor `ReportBuilder`'s 4-way element-building loop | Low | dry-solid-reviewer | Open |
| [004](004-definitionfiller-band-builder-refactor.md) | Collapse `DefinitionFiller`'s 3-way band builder | Low | dry-solid-reviewer | Open |
| [005](005-expression-formatter-block-extraction.md) | Extract shared formatter block across Expression classes | Low | dry-solid-reviewer | Open |
| [006](006-definitionfiller-onband-immutability.md) | Fix `DefinitionFiller::onBand` fluent-returns-but-mutates | Medium | dry-solid-reviewer | ✅ Closed (A1) |
| [007](007-zoom-transform-origin-scroll-bug.md) | Fix zoom transform-origin causing unreachable overflow at >100% | Medium | frontend-designer | Open |
| [008](008-add-vue-tsc-typecheck-step.md) | Add `vue-tsc --noEmit` step to `npm run build` | Medium | frontend-designer | Open |
| [009](009-verify-page-margin-print-rule.md) | Verify or add `@page { margin: 0 }` for print output | Low | frontend-designer | Open |
| [010](010-library-rename-to-edgecase.md) | Rename library from `foreup/reporting` → `edgecase123/report-writer` | Medium | session-design | ✅ Closed (A1) |
| [011](011-zoom-preset-placeholder-behavior.md) | Zoom preset dropdown — decide on placeholder behavior | Low | frontend-designer | Open |
| [012](012-implement-standalone-runtime-subproject-a.md) | Implement standalone runtime (Sub-project A) per approved design | Epic | session-design | Blocked on A1 landing (done) → ready to plan A2 |
| [013](013-brainstorm-structured-form-builder-subproject-b.md) | Brainstorm structured form builder (Sub-project B) | Epic | session-design | Blocked on Sub-project A |
| [014](014-implement-v1-layout-engine.md) | Implement v1 layout engine per `docs/architecture/` specs | Epic | design | Backlog |
| [015](015-implement-user-scripting.md) | Implement user-scripting per `docs/architecture/user-scripting.md` | Epic | design | ❌ Superseded 2026-08-23 by extension-hooks.md + Ticket 016 |
| [016](016-extension-hooks-lifecycle-plus-immutability-retrofit.md) | Add lifecycle hooks to ReportBuilder + DefinitionFiller (immutable-fluent); retrofit `onBand` immutability | Medium | design session 2026-08-23 | Open |
| [017](017-title-alignment-vs-columns-vs-page.md) | Title element width — columns extent vs printable page width | Low | design 2026-08-23 | ✅ Closed (2026-08-23) |
| [018](018-pageconfig-right-margin-and-printable-width.md) | PageConfig missing right margin and printableWidth() | Medium | design 2026-08-23 | ✅ Closed (2026-08-23) |

## Fixed in the same session (no tickets — reference commits)

- Silent mount failure diagnostic in `main.ts`
- Hard-coded prod URL + course ID removed from `index.html`
- TypeScript `^6.0.3` → `^5.3.3` in `package.json` (v6 doesn't exist)
- Zoom step `0.10` → `0.25` in `viewerState.ts` (aligns to preset grid)
- `parseFloat(String(level))` simplified in `viewerState.ts`
- Type-safe error handling in `ReportCanvas.vue` (`e instanceof Error ? …`)
- Empty state distinct from Error state in `ReportCanvas.vue`
- Aria-live regions on Loading and Error in `ReportCanvas.vue`
- Retry button added to Error state in `ReportCanvas.vue`
- Regex HTML parsing → `DOMParser` in `ReportCanvas.vue`
- `<main aria-label="Report content">` landmark in `ReportCanvas.vue`
- Loading/error hidden in `@media print` in `ReportCanvas.vue`
- `role="toolbar"` + `aria-label`s + `focus-visible` ring on `ViewerToolbar.vue`
- `@media print` display-none inside scoped toolbar CSS (belt-and-braces)
- **PHP DRY fix:** extracted `Grouping::byField()` at `writer/src/Instance/Grouping.php`, updated both `ReportBuilder` and `DefinitionFiller` call sites, removed both local implementations

## Promoting to GitHub

```bash
# Single ticket:
gh issue create --repo edgecase123/report-writer \
  --title "Consolidate aggregate math (DefinitionFiller ↔ AggregateExpression)" \
  --body-file docs/tickets/001-aggregate-math-dry-consolidation.md

# Bulk (all open):
for f in docs/tickets/[0-9][0-9][0-9]-*.md; do
    title=$(head -1 "$f" | sed 's/^# TICKET-[0-9]*: //')
    gh issue create --repo edgecase123/report-writer --title "$title" --body-file "$f"
done
```
