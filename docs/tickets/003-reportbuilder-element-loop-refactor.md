# TICKET-003: Refactor `ReportBuilder`'s 4-way element-building loop

**Priority:** Low
**Source:** dry-solid-reviewer audit (2026-08-22) — 🟡 DRY (renderer boilerplate)
**Scope:** `writer/src/Builder/ReportBuilder.php`

## Problem

Four near-identical loops over `$this->columns` — `headerElements`, `detailElements`, `footerElements`, `summaryElements` at lines 132–202. Each iterates columns, calls `applyMargin`, constructs `new ElementInstance(..., new TextContent($expr->evaluate($ctx)), $col->getTextAlign())`. Only the expression selector and the element height differ.

## Proposed fix

Extract a private helper:

```php
private function buildRowElements(
    string $idPrefix,
    EvalContext $ctx,
    callable $exprFor,    // fn(Column $col): ?ContentExpression
    float $height,
): array {
    $elements = [];
    foreach ($this->columns as $col) {
        [$elX, $elW] = $this->applyMargin($col);
        $expr        = $exprFor($col);
        $text        = $expr !== null ? $expr->evaluate($ctx) : '';
        $elements[]  = new ElementInstance(
            "{$idPrefix}_{$col->getId()}", $elX, 0.0, $elW, $height,
            new TextContent($text),
            $col->getTextAlign()
        );
    }
    return $elements;
}
```

Each of the four call sites becomes ~3 lines specifying its expression selector and height.

## Acceptance criteria

- [ ] Four `*Elements` methods collapsed to a single helper + four thin call sites
- [ ] Full phpunit suite passes with no snapshot changes

## Notes

- Low priority — the current code isn't broken, just repetitive. Prioritize when touching `ReportBuilder` for another reason.
- Watch: header uses `new StaticExpression($col->getHeader())` inline while detail/footer/summary use `$col->getDetailExpr()` etc. The selector callable pattern handles the asymmetry cleanly.
