<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Reports\DailySalesFiller;
use ReportWriter\App\Reports\DataSource\SqliteDailySalesProvider;
use ReportWriter\App\Tests\Support\DailySalesFixture;
use ReportWriter\Instance\ReportInstance;

final class DailySalesFillerTest extends TestCase
{
    public function testFillProducesReportInstanceWithExpectedBands(): void
    {
        $pdo      = DailySalesFixture::newPdo();
        $filler   = new DailySalesFiller(new SqliteDailySalesProvider($pdo));
        $instance = $filler->fill(['date' => '2026-08-22']);

        $this->assertInstanceOf(ReportInstance::class, $instance);
        $this->assertSame('daily-sales', $instance->getReportInstanceId());

        // Non-empty band list; at least one band per row plus title.
        $bands = $instance->getBandInstances();
        $this->assertNotEmpty($bands, 'filler must produce at least the title band');
        $this->assertGreaterThanOrEqual(4, count($bands),
            'expected at least title + header + 3 detail bands');
    }

    public function testFillProducesFewerBandsWhenNoOrdersForDate(): void
    {
        $pdo           = DailySalesFixture::newPdo();
        $filler        = new DailySalesFiller(new SqliteDailySalesProvider($pdo));
        $instanceEmpty = $filler->fill(['date' => '2020-01-01']);
        $instanceFull  = $filler->fill(['date' => '2026-08-22']);

        $this->assertLessThan(
            count($instanceFull->getBandInstances()),
            count($instanceEmpty->getBandInstances()),
            'empty-date report must have fewer bands than populated-date report'
        );
    }
}
