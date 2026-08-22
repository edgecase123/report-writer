# TICKET-010: Rename library from `foreup/reporting` → `edgecase123/report-writer`

**Priority:** Medium
**Source:** session-design (2026-08-22) — foreUP-neutralisation decision
**Scope:** `writer/composer.json`, every file under `writer/src/**/*.php` and `writer/tests/**/*.php`

## Problem

The library is currently packaged as `foreup/reporting` with PSR-4 namespace `foreup\Reporting\`. Per [ADR-013](../09-conventions/decisions/013-framework-agnostic-library.md), the library is framework-agnostic PHP intended for adoption by Symfony 5.x/7, Laravel 10+, or plain PHP consumers. A framework-agnostic library needs a framework-agnostic name — `foreup/reporting` implies foreUP ownership and a specific consumer, both incorrect. The library must rename.

## Proposed fix

Mechanical find-replace across ~30 files:

1. **`writer/composer.json`:**
   ```diff
   -"name": "foreup/reporting",
   +"name": "edgecase123/report-writer",
   ...
   -"psr-4": { "foreup\\Reporting\\": "src/" }
   +"psr-4": { "ReportWriter\\": "src/" }
   ...
   -"psr-4": { "foreup\\Reporting\\Tests\\": "tests/" }
   +"psr-4": { "ReportWriter\\Tests\\": "tests/" }
   ```

2. **Every `writer/src/**/*.php` and `writer/tests/**/*.php`:**
   - `namespace foreup\Reporting\...` → `namespace ReportWriter\...`
   - `use foreup\Reporting\...` → `use ReportWriter\...`

3. **`writer/README.md`** — strip foreUP references, update code examples to new namespace.

4. **After rewrite:**
   ```bash
   cd writer && composer dump-autoload && vendor/bin/phpunit
   ```

## Acceptance criteria

- [ ] `composer.json` package name and both PSR-4 mappings updated
- [ ] Zero remaining `foreup\Reporting` / `foreup/reporting` references in `writer/**` (excluding CHANGELOG or historical notes)
- [ ] `composer dump-autoload` succeeds
- [ ] Full phpunit suite passes with no test changes required
- [ ] `writer/README.md` foreUP-neutralised

## Notes

- Land in the same PR as [Ticket 002 (dead code deletion)](002-delete-dead-code-definition-namespace.md) so the renamed library ships lean.
- Any consumer of the library (Symfony, Laravel, or plain PHP) will need one update: change `use foreup\Reporting\...` to `use ReportWriter\...` in their code. Mechanical find-replace, one-time.
- Docs and READMEs under `docs/` also reference `foreup\Reporting` in places — update in the same PR (grep `docs/` for `foreup`).
- Post-rename, the library's `writer/README.md` should show wiring examples for multiple frameworks (per [ADR-013](../09-conventions/decisions/013-framework-agnostic-library.md)) rather than just Symfony — Laravel and plain PHP examples get equal footing.
