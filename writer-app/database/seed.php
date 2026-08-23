<?php

declare(strict_types=1);

namespace ReportWriter\App\Database;

use PDO;

// Guard against re-declaration when this file is `require`d more than once
// (the determinism test seeds twice from a single process).
if (class_exists(Seed::class, false)) {
    return;
}

/**
 * Deterministic coffee-shop seed for the demo.
 *
 * Contract per ADR-002: mt_srand(1); ~90 days of activity ending on a fixed
 * anchor date (2026-08-22 UTC). Populates categories, items, staff, orders,
 * order_items, payments. A5 will add template_drafts.
 */
final class Seed
{
    private const ANCHOR_DATE       = '2026-08-22';
    private const DAYS_OF_HISTORY   = 90;
    private const ORDERS_PER_DAY_MIN = 8;
    private const ORDERS_PER_DAY_MAX = 24;

    public static function run(PDO $pdo): void
    {
        mt_srand(1);

        $pdo->beginTransaction();
        try {
            self::wipe($pdo);
            self::insertCategories($pdo);
            self::insertItems($pdo);
            self::insertStaff($pdo);
            self::insertOrdersAndItems($pdo);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function wipe(PDO $pdo): void
    {
        $pdo->exec('DELETE FROM payments');
        $pdo->exec('DELETE FROM order_items');
        $pdo->exec('DELETE FROM orders');
        $pdo->exec('DELETE FROM items');
        $pdo->exec('DELETE FROM staff');
        $pdo->exec('DELETE FROM categories');
    }

    private static function insertCategories(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT INTO categories (id, name) VALUES (:id, :name)');
        foreach ([[1, 'Coffee'], [2, 'Tea'], [3, 'Pastry'], [4, 'Sandwich']] as [$id, $name]) {
            $stmt->execute(['id' => $id, 'name' => $name]);
        }
    }

    private static function insertItems(PDO $pdo): void
    {
        $catalogue = [
            // [id, category_id, name, price_cents]
            [1, 1, 'Espresso',      350],
            [2, 1, 'Americano',     400],
            [3, 1, 'Latte',         500],
            [4, 1, 'Cappuccino',    500],
            [5, 1, 'Cold Brew',     550],
            [6, 2, 'Black Tea',     350],
            [7, 2, 'Green Tea',     350],
            [8, 2, 'Chai Latte',    475],
            [9, 3, 'Croissant',     400],
            [10, 3, 'Muffin',       350],
            [11, 3, 'Scone',        375],
            [12, 4, 'Turkey Club',  1050],
            [13, 4, 'Veggie Wrap',  950],
            [14, 4, 'Grilled Ham',  1100],
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO items (id, category_id, name, unit_price_cents) VALUES (:id, :cat, :name, :price)'
        );
        foreach ($catalogue as [$id, $cat, $name, $price]) {
            $stmt->execute(['id' => $id, 'cat' => $cat, 'name' => $name, 'price' => $price]);
        }
    }

    private static function insertStaff(PDO $pdo): void
    {
        $roster = [
            [1, 'Ada Lovelace',    'barista'],
            [2, 'Ben Carson',      'barista'],
            [3, 'Cleo Diaz',       'barista'],
            [4, 'Devon Ellis',     'barista'],
            [5, 'Farah Grant',     'shift_lead'],
            [6, 'Hiro Yamamoto',   'manager'],
        ];
        $stmt = $pdo->prepare('INSERT INTO staff (id, name, role) VALUES (:id, :name, :role)');
        foreach ($roster as [$id, $name, $role]) {
            $stmt->execute(['id' => $id, 'name' => $name, 'role' => $role]);
        }
    }

    private static function insertOrdersAndItems(PDO $pdo): void
    {
        $orderStmt = $pdo->prepare(
            'INSERT INTO orders (id, opened_at, closed_at) VALUES (:id, :opened, :closed)'
        );
        $lineStmt = $pdo->prepare(
            'INSERT INTO order_items (order_id, item_id, quantity, unit_price_cents) VALUES (:oid, :iid, :qty, :price)'
        );

        // Pre-computed catalogue for random picks.
        $itemIds    = range(1, 14);
        $itemPrices = self::pricesById($pdo);

        $orderId = 1;
        for ($dayOffset = self::DAYS_OF_HISTORY; $dayOffset >= 0; $dayOffset--) {
            $day        = date('Y-m-d', strtotime(self::ANCHOR_DATE . " -{$dayOffset} days"));
            $ordersToday = mt_rand(self::ORDERS_PER_DAY_MIN, self::ORDERS_PER_DAY_MAX);

            for ($i = 0; $i < $ordersToday; $i++) {
                $openHour   = mt_rand(6, 20);
                $openMin    = mt_rand(0, 59);
                $durMin     = mt_rand(2, 20);
                $opened     = sprintf('%sT%02d:%02d:00Z', $day, $openHour, $openMin);
                $closedTs   = strtotime($opened) + $durMin * 60;
                $closed     = gmdate('Y-m-d\TH:i:s\Z', $closedTs);

                $orderStmt->execute(['id' => $orderId, 'opened' => $opened, 'closed' => $closed]);

                $lineCount = mt_rand(1, 4);
                for ($j = 0; $j < $lineCount; $j++) {
                    $iid = $itemIds[mt_rand(0, count($itemIds) - 1)];
                    $qty = mt_rand(1, 3);
                    $lineStmt->execute([
                        'oid'   => $orderId,
                        'iid'   => $iid,
                        'qty'   => $qty,
                        'price' => $itemPrices[$iid],
                    ]);
                }
                $orderId++;
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private static function pricesById(PDO $pdo): array
    {
        $out = [];
        foreach ($pdo->query('SELECT id, unit_price_cents FROM items') as $row) {
            $out[(int) $row['id']] = (int) $row['unit_price_cents'];
        }
        return $out;
    }
}
