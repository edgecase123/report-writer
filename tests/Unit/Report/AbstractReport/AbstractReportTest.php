<?php

declare(strict_types=1);

namespace ReportWrite\Tests\Unit\Report\AbstractReport;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use ReportWriter\Report\Data\DataProviderInterface;
use ReportWriter\Tests\Unit\Report\AbstractTestReport;

class AbstractReportTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testRender(): void
    {
        $iterator = new \ArrayIterator([]);

        $dataProvider = \Mockery::mock(DataProviderInterface::class);
        $dataProvider->shouldReceive('getRecords')
            ->once()
            ->andReturn($iterator);

        $report = (new AbstractTestReport())->setDataProvider($dataProvider);
        $report->render();

        $this->assertNotNull($report);
    }
}
