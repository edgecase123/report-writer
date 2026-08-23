<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Support;

use PDO;
use ReportWriter\App\Database\SqliteConnectionFactory;

/**
 * Inserts a deterministic mini-dataset used by A2's unit + smoke tests.
 *
 * On the date 2026-08-22 UTC: 3 closed orders totalling 2900 cents.
 * On the date 2026-08-21 UTC: 1 closed order (must be excluded by the report).
 * One unclosed order (must be excluded).
 *
 * This is NOT the demo seed (Task 9). It is a targeted fixture for tests.
 * A6 will replace it with a shared `coffee-shop-mini.sql` snapshot fixture.
 */
final class DailySalesFixture
{
    public static function newPdo(): \PDO
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../database/schema.sql'
        );
        self::load($pdo);
        return $pdo;
    }

    public static function load(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO categories (id, name) VALUES (1, 'Coffee'), (2, 'Pastry')");
        $pdo->exec("INSERT INTO items (id, category_id, name, unit_price_cents) VALUES
            (1, 1, 'Espresso', 500),
            (2, 1, 'Latte',    600),
            (3, 2, 'Croissant', 400)");

        // Orders on the target date (2026-08-22).
        $pdo->exec("INSERT INTO orders (id, opened_at, closed_at) VALUES
            (1001, '2026-08-22T09:10:00Z', '2026-08-22T09:15:00Z'),
            (1002, '2026-08-22T10:20:00Z', '2026-08-22T10:22:00Z'),
            (1003, '2026-08-22T14:00:00Z', '2026-08-22T14:05:00Z')");
        $pdo->exec("INSERT INTO order_items (id, order_id, item_id, quantity, unit_price_cents) VALUES
            (1, 1001, 1, 1, 500),          -- 500
            (2, 1001, 3, 1, 400),          -- 400  → order 1001 = 900
            (3, 1002, 2, 2, 600),          -- 1200 → order 1002 = 1200
            (4, 1003, 1, 1, 500),          -- 500
            (5, 1003, 3, 1, 300)           -- 300  → order 1003 = 800
        ");

        // Prior-day order (should not appear).
        $pdo->exec("INSERT INTO orders (id, opened_at, closed_at) VALUES
            (999, '2026-08-21T09:00:00Z', '2026-08-21T09:10:00Z')");
        $pdo->exec("INSERT INTO order_items (id, order_id, item_id, quantity, unit_price_cents) VALUES
            (99, 999, 2, 1, 600)");

        // Still-open order (should not appear regardless of date).
        $pdo->exec("INSERT INTO orders (id, opened_at, closed_at) VALUES
            (2000, '2026-08-22T15:00:00Z', NULL)");
        $pdo->exec("INSERT INTO order_items (id, order_id, item_id, quantity, unit_price_cents) VALUES
            (200, 2000, 1, 1, 500)");
    }
}
