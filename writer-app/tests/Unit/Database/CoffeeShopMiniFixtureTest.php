<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;

final class CoffeeShopMiniFixtureTest extends TestCase
{
    private const FIXTURE_PATH = __DIR__ . '/../../Fixtures/coffee-shop-mini.sql';

    public function testFixtureLoadsIntoFreshSchemaAndHasExpectedRowCounts(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../database/schema.sql'
        );
        $pdo->exec((string) file_get_contents(self::FIXTURE_PATH));

        $this->assertSame(2, $this->countRows($pdo, 'categories'));
        $this->assertSame(4, $this->countRows($pdo, 'items'));
        $this->assertSame(2, $this->countRows($pdo, 'staff'));
        $this->assertSame(5, $this->countRows($pdo, 'orders'));       // 3 target-day + 1 prior + 1 open
        $this->assertSame(7, $this->countRows($pdo, 'order_items'));  // 5 target-day + 1 prior + 1 open
        $this->assertSame(4, $this->countRows($pdo, 'payments'));     // 3 target-day + 1 prior; open tab has none
    }

    public function testFixtureIncludesOneOpenTab(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../database/schema.sql'
        );
        $pdo->exec((string) file_get_contents(self::FIXTURE_PATH));

        $openCount = (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE closed_at IS NULL')->fetchColumn();
        $this->assertSame(1, $openCount, 'Open Tabs report needs at least one closed_at IS NULL row');
    }

    public function testFixturePaymentAmountsMatchOrderTotals(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../database/schema.sql'
        );
        $pdo->exec((string) file_get_contents(self::FIXTURE_PATH));

        $mismatches = $pdo->query(
            "SELECT p.order_id
               FROM payments p
               JOIN (
                   SELECT order_id, SUM(quantity * unit_price_cents) AS total_cents
                     FROM order_items
                    GROUP BY order_id
               ) t ON t.order_id = p.order_id
              WHERE p.amount_cents != t.total_cents"
        )->fetchAll();
        $this->assertSame([], $mismatches, 'each hand-authored payment.amount_cents must match SUM(order_items)');
    }

    private function countRows(\PDO $pdo, string $table): int
    {
        return (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }
}
