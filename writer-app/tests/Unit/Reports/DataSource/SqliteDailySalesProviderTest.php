<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports\DataSource;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Reports\DataSource\SqliteDailySalesProvider;
use ReportWriter\App\Tests\Support\DailySalesFixture;

final class SqliteDailySalesProviderTest extends TestCase
{
    public function testReturnsRowsForRequestedDateExcludingOpenAndOtherDates(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../../database/schema.sql'
        );
        DailySalesFixture::load($pdo);

        $provider = new SqliteDailySalesProvider($pdo);
        $rows     = $provider->fetchRows(['date' => '2026-08-22']);

        $this->assertSame(
            [
                ['order_id' => 1001, 'closed_at' => '09:15', 'total_cents' => 900],
                ['order_id' => 1002, 'closed_at' => '10:22', 'total_cents' => 1200],
                ['order_id' => 1003, 'closed_at' => '14:05', 'total_cents' => 800],
            ],
            $rows
        );
    }

    public function testReturnsEmptyArrayForDateWithNoClosedOrders(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../../database/schema.sql'
        );
        DailySalesFixture::load($pdo);

        $provider = new SqliteDailySalesProvider($pdo);
        $this->assertSame([], $provider->fetchRows(['date' => '2020-01-01']));
    }
}
