# TICKET-004: Collapse `DefinitionFiller`'s 3-way band builder

**Priority:** Low
**Source:** dry-solid-reviewer audit (2026-08-22) — 🟡 DRY (renderer boilerplate)
**Scope:** `writer/src/Fill/DefinitionFiller.php`

## Problem

Three near-identical band-builder methods at lines 191–219: `staticBand`, `rowBand`, `aggregateBand`. Each iterates `$def->getElements()`, calls `resolveElement` with a different combination of `$row` / `$aggregateRows` / `$groupValue`, then wraps the result in a `BandInstance`. Only the arguments to `resolveElement` differ.

## Proposed fix

Collapse to a single `buildBand()`:

```php
private function buildBand(
    BandTemplate $def,
    array $row,
    array $aggregateRows,
    ?string $groupValue,
    array $params,
    ?string $keySuffix,   // null | rowKey | groupValue
): BandInstance {
    $bandId   = 'band_' . $def->getId() . ($keySuffix !== null ? '_' . $this->safeId($keySuffix) : '');
    $elements = [];
    foreach ($def->getElements() as $elDef) {
        $elements[] = $this->resolveElement($elDef, $row, $aggregateRows, $groupValue, $params, $bandId);
    }
    return new BandInstance($bandId, $def->getType(), $elements, null, $def->getRowSpacing());
}
```

The three call sites in `buildBands()` already know which arguments are meaningful for their band type — they just pass empty arrays for the ones that aren't.

## Acceptance criteria

- [ ] Three `*Band()` methods collapsed to one `buildBand()`
- [ ] Full phpunit suite passes with no snapshot changes

## Notes

- Low priority — mirror to Ticket 003. Do when touching `DefinitionFiller` for another reason.
