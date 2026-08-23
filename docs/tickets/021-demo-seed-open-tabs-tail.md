# TICKET-021: Demo seed should leave a small tail of open tabs

**Status:** Open
**Priority:** Low (demo polish — surfaced during A3.3 peer review)
**Source:** peer review of PR #21 (A3.3 Open Tabs plan) — noticed the current `Seed::run` closes every order, so `/api/reports/open-tabs` renders empty in the demo
**Scope:** `writer-app/database/seed.php`, `writer-app/tests/Unit/Database/SeedDeterminismTest.php`

## Problem

After A3.3 lands, `/api/reports/open-tabs` returns HTML with the title + column headers + **zero detail rows** when hit against the demo seed. That's not a correctness bug — the snapshot test proves the report works against the fixture — but it's a poor demo experience. A reviewer or new contributor loading the demo sees an empty report and can't tell whether the feature works or is broken.

Root cause in `writer-app/database/seed.php`, `Seed::insertOrdersAndItems()`:

```php
$closedTs = strtotime($opened) + $durMin * 60;
$pdo->exec("INSERT INTO orders (id, opened_at, closed_at) VALUES ({$id}, '{$opened}', '{$closedZ}')");
```

Every order gets both `opened_at` and `closed_at` set. There is no branch that produces `closed_at IS NULL`, so the `payments` seed's "one per closed order" invariant lines up with `orders` 1:1 and the open-tabs report has nothing to render.

## Design shape

**Approach:** at the end of `insertOrdersAndItems()`, leave the last ~5 orders on the anchor date (`2026-08-22`) open (`closed_at = NULL`). These represent tabs that were still open when the demo snapshot was captured — the exact semantic of a real open-tabs report.

**Constraints:**

- **Byte-identical historical orders.** Every order *before* the anchor date must render identically to today. Only the tail on the anchor date gets a NULL `closed_at`.
- **Payments seed already assumes 1:1 with closed orders** — that invariant survives naturally (`insertPayments` iterates only orders where `closed_at IS NOT NULL`).
- **PRNG stream preservation.** Adding a conditional at the end of the anchor-day loop should not perturb the mt_rand stream for earlier orders. Verify with the same in-process PHP diff approach A3.1 used for byte-identity.
- **Determinism.** `SeedDeterminismTest::seedAndDump()` already dumps `orders` — the diff will surface any drift. Extend the fixture-count expectations if needed.

**Implementation sketch (illustrative, not final):**

```php
private static function insertOrdersAndItems(PDO $pdo): void
{
    // ... existing loop over dates + orders ...
    foreach ($ordersOnAnchorDay as $index => $orderRow) {
        // ... existing insert logic ...
        $isOpenTail = ($index >= $openTailStart);   // ← last N on anchor day
        $closedAt   = $isOpenTail ? 'NULL' : "'{$closedZ}'";
        $pdo->exec("INSERT INTO orders (id, opened_at, closed_at) VALUES ({$id}, '{$opened}', {$closedAt})");
    }
}
```

Exact tail size is implementer's choice — 3 to 8 open tabs is a reasonable range (enough to see a list, few enough not to dominate the demo).

## Acceptance criteria

- [ ] `Seed::run` produces a small tail of open tabs (3–8) on the anchor date `2026-08-22`. No open tabs on any other date.
- [ ] `SELECT COUNT(*) FROM orders WHERE closed_at IS NULL` returns a small positive integer after seeding — pinned in a new test assertion.
- [ ] `SeedDeterminismTest` still passes.
- [ ] Historical orders (any date < anchor date, and any closed order on the anchor date) render byte-identical to `main` before this change — verify via `sqlite3` dump diff.
- [ ] `payments` row count == count of closed orders (existing invariant survives).
- [ ] `/api/reports/open-tabs` in the running demo renders a non-empty detail list.
- [ ] `writer-app` PHPUnit suite green.

## Out of scope

- Extending the fixture `coffee-shop-mini.sql` — it already has an open tab; no change needed.
- Multiple payment splits per order or additional payment methods — separate concern.
- Any change to `orders.closed_at` schema — column is already nullable per A3.1 §Task 1.

## Notes

- Filed 2026-08-23 during peer review of PR #21 (A3.3 Open Tabs plan).
- No SLA. Pick up whenever the demo polish is worth an hour.
- Related: [A3.3 plan](../superpowers/plans/2026-08-23-a3.3-open-tabs.md) Task 6 Step 3 explicitly notes the current demo behavior and points here for the fix.
