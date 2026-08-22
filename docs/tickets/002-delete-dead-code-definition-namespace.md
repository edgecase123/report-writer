# TICKET-002: Delete dead code — `Interfaces/DataProviderInterface` + entire `Definition/*` namespace

**Status:** ✅ Closed (2026-08-22, commit `745f9e3`, A1 plan Task 3)
**Priority:** Medium
**Source:** dry-solid-reviewer audit (2026-08-22) — 🟡 DRY / dead code
**Scope:** `writer/src/Interfaces/DataProviderInterface.php`, `writer/src/Definition/*.php`

## Problem

Two chunks of unreferenced code:

1. **`writer/src/Interfaces/DataProviderInterface.php`** — defined and never used anywhere in `writer/src/` or `writer/tests/`. `ReportDataSourceInterface` is the actual data-provider seam.
2. **`writer/src/Definition/` directory** — `ReportDefinition.php`, `BandDefinition.php`, `ElementDefinition.php` are all orphaned. Runtime code uses `Template/ReportTemplate`, `Template/BandTemplate`, `Template/ElementTemplate` for the same shapes.

Two parallel type hierarchies for the same domain concept — a DRY smell waiting to grow a third caller.

## Proposed fix

Delete both:

```bash
rm writer/src/Interfaces/DataProviderInterface.php
rm -rf writer/src/Definition/
```

Then verify:

```bash
grep -rn "DataProviderInterface\|foreup\\\\Reporting\\\\Definition\\\\" writer/ | grep -v "^Binary\|vendor\|node_modules"
# Expected: no output
composer dump-autoload
vendor/bin/phpunit
```

## Acceptance criteria

- [ ] Both files/directory removed
- [ ] No remaining references to `DataProviderInterface` or `foreup\Reporting\Definition\*` anywhere in the repo
- [ ] `composer dump-autoload` succeeds
- [ ] Full phpunit suite passes

## Notes

- Land in the same PR as [Ticket 010 (library rename)](010-library-rename-to-edgecase.md) so the new namespace ships lean.
- If the `Definition/*` hierarchy was intended as a future public API distinct from `Template/*`, revisit the design first — but grep shows zero external callers, so shipping it as unused surface makes no sense.
