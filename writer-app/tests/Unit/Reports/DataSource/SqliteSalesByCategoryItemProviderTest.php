<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports\DataSource;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Reports\DataSource\SqliteSalesByCategoryItemProvider;

final class SqliteSalesByCategoryItemProviderTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../../Fixtures/coffee-shop-mini.sql';
    private const SCHEMA  = __DIR__ . '/../../../../database/schema.sql';

    public function testFetchRowsReturnsOneRowPerCategoryItemWithQuantityAndTotal(): void
    {
        // Fixture (date 2026-08-22) rows:
        //   Coffee/Espresso  — order 1001 (1x500) + order 1003 (1x500) = qty 2, total 1000
        //   Coffee/Latte     — order 1002 (2x600)                       = qty 2, total 1200
        //   Pastry/Croissant — order 1001 (1x400)                       = qty 1, total  400
        //   Pastry/Muffin    — order 1003 (1x350)                       = qty 1, total  350
        // Sort: category ASC, total DESC, item ASC.
        $rows = $this->fetch(['date' => '2026-08-22']);

        $this->assertSame(
            [
                ['category_name' => 'Coffee', 'item_name' => 'Latte',     'quantity_sold' => 2, 'total_cents' => 1200],
                ['category_name' => 'Coffee', 'item_name' => 'Espresso',  'quantity_sold' => 2, 'total_cents' => 1000],
                ['category_name' => 'Pastry', 'item_name' => 'Croissant', 'quantity_sold' => 1, 'total_cents' =>  400],
                ['category_name' => 'Pastry', 'item_name' => 'Muffin',    'quantity_sold' => 1, 'total_cents' =>  350],
            ],
            $rows
        );
    }

    public function testFetchRowsExcludesOtherDates(): void
    {
        // Fixture has a 2026-08-21 latte (order 999) — must not appear in 2026-08-22 totals.
        $rows = $this->fetch(['date' => '2026-08-22']);
        $latteRow = null;
        foreach ($rows as $r) {
            if ($r['item_name'] === 'Latte') {
                $latteRow = $r;
            }
        }
        $this->assertNotNull($latteRow, 'Latte must appear for 2026-08-22');
        $this->assertSame(2,    $latteRow['quantity_sold'], 'prior-day Latte must NOT roll into 2026-08-22');
        $this->assertSame(1200, $latteRow['total_cents']);
    }

    public function testFetchRowsExcludesOpenTabs(): void
    {
        // Fixture order 2000 is an open tab with 1 espresso — must not add to Espresso totals.
        $rows = $this->fetch(['date' => '2026-08-22']);
        $espressoRow = null;
        foreach ($rows as $r) {
            if ($r['item_name'] === 'Espresso') {
                $espressoRow = $r;
            }
        }
        $this->assertNotNull($espressoRow);
        $this->assertSame(2,    $espressoRow['quantity_sold'], 'open-tab espresso must NOT roll into totals');
        $this->assertSame(1000, $espressoRow['total_cents']);
    }

    public function testFetchRowsReturnsEmptyForDateWithNoClosedOrders(): void
    {
        $this->assertSame([], $this->fetch(['date' => '2020-01-01']));
    }

    public function testFetchRowsRejectsMalformedDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fetch(['date' => 'yesterday']);
    }

    public function testFetchRowsRejectsMissingDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->fetch([]);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array{category_name: string, item_name: string, quantity_sold: int, total_cents: int}>
     */
    private function fetch(array $params): array
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(self::SCHEMA);
        $pdo->exec((string) file_get_contents(self::FIXTURE));
        return (new SqliteSalesByCategoryItemProvider($pdo))->fetchRows($params);
    }
}
