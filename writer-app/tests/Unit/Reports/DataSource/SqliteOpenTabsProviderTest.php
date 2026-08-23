<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports\DataSource;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Reports\DataSource\SqliteOpenTabsProvider;

final class SqliteOpenTabsProviderTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../../Fixtures/coffee-shop-mini.sql';
    private const SCHEMA  = __DIR__ . '/../../../../database/schema.sql';

    public function testFetchRowsReturnsOnlyOrdersWithClosedAtNull(): void
    {
        $rows = $this->fetch();

        $this->assertSame(
            [
                [
                    'order_id'            => 2000,
                    'opened_at'           => '2026-08-22 15:00',
                    'running_total_cents' => 500,
                ],
            ],
            $rows
        );
    }

    public function testFetchRowsIgnoresParams(): void
    {
        $this->assertSame(
            $this->fetch(),
            (new SqliteOpenTabsProvider($this->seededPdo()))->fetchRows(['date' => '2026-08-22', 'garbage' => 'x'])
        );
    }

    public function testFetchRowsOrdersByOpenedAtAscending(): void
    {
        $pdo = $this->seededPdo();
        $pdo->exec("INSERT INTO orders (id, opened_at, closed_at)
                    VALUES (2001, '2026-08-22T08:30:00Z', NULL)");
        $pdo->exec("INSERT INTO order_items (id, order_id, item_id, quantity, unit_price_cents)
                    VALUES (201, 2001, 2, 1, 600)");

        $rows = (new SqliteOpenTabsProvider($pdo))->fetchRows([]);
        $orderIds = array_column($rows, 'order_id');
        $this->assertSame([2001, 2000], $orderIds, 'earlier opened_at must appear first');
    }

    public function testFetchRowsReturnsEmptyWhenNoOpenTabsExist(): void
    {
        $pdo = $this->seededPdo();
        $pdo->exec("UPDATE orders SET closed_at = '2026-08-22T15:05:00Z' WHERE id = 2000");
        $this->assertSame([], (new SqliteOpenTabsProvider($pdo))->fetchRows([]));
    }

    public function testFetchRowsHandlesOpenTabWithZeroItems(): void
    {
        $pdo = $this->seededPdo();
        $pdo->exec("INSERT INTO orders (id, opened_at, closed_at)
                    VALUES (2002, '2026-08-22T16:00:00Z', NULL)");

        $rows = (new SqliteOpenTabsProvider($pdo))->fetchRows([]);
        $totals = [];
        foreach ($rows as $r) {
            $totals[$r['order_id']] = $r['running_total_cents'];
        }
        $this->assertSame(0, $totals[2002] ?? null, 'zero-item open tab must have 0 running total');
    }

    /**
     * @return array<int, array{order_id: int, opened_at: string, running_total_cents: int}>
     */
    private function fetch(): array
    {
        return (new SqliteOpenTabsProvider($this->seededPdo()))->fetchRows([]);
    }

    private function seededPdo(): \PDO
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(self::SCHEMA);
        $pdo->exec((string) file_get_contents(self::FIXTURE));
        return $pdo;
    }
}
