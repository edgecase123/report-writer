<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Report\AbstractReport;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use ReportWriter\Report\AbstractReport;
use ReportWriter\Report\Builder\GroupBuilder;
use ReportWriter\Report\Data\DataProviderInterface;
use ReportWriter\Tests\Unit\Report\AbstractTestReport;

class MultiLevelGroupingTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testRendersNestedGroupsCorrectBandOrderAndPerLevelAggregates(): void
    {
        // Sample data with two grouping levels
        $records = [
            ['year' => 2024, 'month' => 'January', 'amount' => 100],
            ['year' => 2024, 'month' => 'January', 'amount' => 200],
            ['year' => 2024, 'month' => 'February', 'amount' => 300],
            ['year' => 2025, 'month' => 'January', 'amount' => 400],
        ];

        $iterator = new \ArrayIterator($records);

        $dataProvider = \Mockery::mock(DataProviderInterface::class);
        $dataProvider->shouldReceive('getRecords')->once()->andReturn($iterator);

        $report = new AbstractTestReport();

        $report->setDataProvider($dataProvider)
            ->setGroups([
                (new GroupBuilder('year'))->sum('amount', 'yearTotal'),
                (new GroupBuilder('month'))->sum('amount', 'monthTotal'),
            ]);

        $report->render();

        $bands = $report->getRenderedBands();
        $names = array_column($bands, 'name');

        // Expected band order
        $this->assertEquals([
            'reportHeader',
            'groupHeader_0',  // 2024
            'groupHeader_1',  // January
            'detail', 'detail',
            'groupFooter_1',  // January footer
            'groupHeader_1',  // February
            'detail',
            'groupFooter_1',  // February footer
            'groupFooter_0',  // 2024 footer
            'groupHeader_0',  // 2025
            'groupHeader_1',  // January
            'detail',
            'groupFooter_1',
            'groupFooter_0',
            'summary',
            'reportFooter',
        ], $names);

        // --------------------------------------------------------------------
        // Collect footers for easier assertion
        // --------------------------------------------------------------------
        $innerFooters = []; // groupFooter_1 → monthTotal
        $outerFooters = []; // groupFooter_0 → yearTotal

        foreach ($bands as $band) {
            if ($band['name'] === 'groupFooter_1') {
                $innerFooters[] = $band['context'];
            }
            if ($band['name'] === 'groupFooter_0') {
                $outerFooters[] = $band['context'];
            }
        }

        $this->assertCount(3, $innerFooters, 'Expected three month footers');
        $this->assertCount(2, $outerFooters, 'Expected two year footers');

        // --------------------------------------------------------------------
        // Month-level aggregates (monthTotal)
        // --------------------------------------------------------------------
        // January 2024: 100 + 200 = 300
        $this->assertEquals(300.0, $innerFooters[0]['monthTotal']);
        $this->assertEquals('January', $innerFooters[0]['firstRecord']['month']);

        // February 2024: 300
        $this->assertEquals(300.0, $innerFooters[1]['monthTotal']);
        $this->assertEquals('February', $innerFooters[1]['firstRecord']['month']);

        // January 2025: 400
        $this->assertEquals(400.0, $innerFooters[2]['monthTotal']);
        $this->assertEquals('January', $innerFooters[2]['firstRecord']['month']);

        // --------------------------------------------------------------------
        // Year-level aggregates (yearTotal)
        // Note: yearTotal should accumulate across all months in the year
        // --------------------------------------------------------------------
        // 2024: 100 + 200 + 300 = 600
        $this->assertEquals(600.0, $outerFooters[0]['yearTotal']);
        $this->assertEquals(2024, $outerFooters[0]['firstRecord']['year']);

        // 2025: 400
        $this->assertEquals(400.0, $outerFooters[1]['yearTotal']);
        $this->assertEquals(2025, $outerFooters[1]['firstRecord']['year']);
    }

    public function testGroupHeaderDisplaysCorrectValueWhenGroupedByDifferentField(): void
    {
        $records = [
            ['id' => 1, 'department' => 'Sales',    'amount' => 100],
            ['id' => 2, 'department' => 'Sales',    'amount' => 200],
            ['id' => 3, 'department' => 'Marketing','amount' => 300],
        ];

        $iterator = new \ArrayIterator($records);
        $dataProvider = \Mockery::mock(DataProviderInterface::class);
        $dataProvider->shouldReceive('getRecords')->once()->andReturn($iterator);

        $report = new class extends AbstractReport {
            public array $renderedBands = [];
            protected function renderBand(string $type, ?int $level = null, $context = null): string
            {
                $name = $level !== null ? $type . '_' . $level : $type;
                $this->renderedBands[] = ['name' => $name, 'context' => $context];
                return '';
            }
        };

        $groupBuilder = (new GroupBuilder('department'))->sum('sumAmount', 'amount');

        $report
            ->setDataProvider($dataProvider)
            ->setGroups([$groupBuilder])
            ->render();

        // Find the first groupHeader_0 context
        $headerContext = null;
        foreach ($report->renderedBands as $band) {
            if ($band['name'] === 'groupHeader_0') {
                $headerContext = $band['context'];
                break;
            }
        }

        $this->assertNotNull($headerContext);
        $this->assertArrayHasKey('groupValue', $headerContext);
        $this->assertEquals('Sales', $headerContext['groupValue']);
    }
}