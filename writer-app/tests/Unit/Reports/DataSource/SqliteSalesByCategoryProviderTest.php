<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports\DataSource;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Reports\DataSource\SqliteSalesByCategoryProvider;

final class SqliteSalesByCategoryProviderTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../../Fixtures/coffee-shop-mini.sql';
    private const SCHEMA  = __DIR__ . '/../../../../database/schema.sql';

    public function testFetchRowsReturnsCategoryTotalsOrderedByTotalDesc(): void
    {
        $rows = $this->fetch(['date' => '2026-08-22']);

        $this->assertSame(
            [
                ['category_name' => 'Coffee', 'total_cents' => 2200],
                ['category_name' => 'Pastry', 'total_cents' => 750],
            ],
            $rows
        );
    }

    public function testFetchRowsExcludesOtherDates(): void
    {
        $rows = $this->fetch(['date' => '2026-08-22']);
        $coffeeTotal = 0;
        foreach ($rows as $row) {
            if ($row['category_name'] === 'Coffee') {
                $coffeeTotal = $row['total_cents'];
            }
        }
        $this->assertSame(2200, $coffeeTotal, 'prior-day latte must not roll into 2026-08-22 Coffee total');
    }

    public function testFetchRowsExcludesOpenTabs(): void
    {
        $rows = $this->fetch(['date' => '2026-08-22']);
        $coffeeTotal = 0;
        foreach ($rows as $row) {
            if ($row['category_name'] === 'Coffee') {
                $coffeeTotal = $row['total_cents'];
            }
        }
        $this->assertSame(2200, $coffeeTotal, 'open-tab items must not roll into any total');
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
     * @return array<int, array{category_name: string, total_cents: int}>
     */
    private function fetch(array $params): array
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(self::SCHEMA);
        $pdo->exec((string) file_get_contents(self::FIXTURE));
        return (new SqliteSalesByCategoryProvider($pdo))->fetchRows($params);
    }
}
