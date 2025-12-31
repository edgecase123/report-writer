<?php

declare(strict_types=1);

namespace ReportWrite\Tests\Unit\Report\AbstractReport;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use ReportWriter\Report\Builder\ReportBuilder;
use ReportWriter\Report\Data\DataProviderInterface;
use ReportWriter\Report\Renderer\JsonRenderer;
use ReportWriter\Report\Report;
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

    public function testGroupLevelCalculationReceivesFinalAggregateValues(): void
    {
        // ------------------------------------------------------------------
        // 1. Prepare sample data
        // ------------------------------------------------------------------
        $records = [
            ['category' => 'A', 'amount' => 10],
            ['category' => 'A', 'amount' => 20],
            ['category' => 'B', 'amount' => 30],
            ['category' => 'B', 'amount' => 40],
        ];

        $dataProvider = \Mockery::mock(DataProviderInterface::class);
        $dataProvider->shouldReceive('getRecords')
            ->once()
            ->andReturn(new \ArrayIterator($records));

        // ------------------------------------------------------------------
        // 2. Build the report using the fluent builder
        // ------------------------------------------------------------------
        $jsonRenderer = new JsonRenderer();

        $report = (new Report())
            ->setRenderer($jsonRenderer)
            ->setDataProvider($dataProvider)
            ->setColumns([
                'category' => 'Category',
                'amount'   => ['label' => 'Amount', 'format' => null],
            ]);

        // Use the builder API to define grouping plus calculation
        $builder = new ReportBuilder($report);

        $builder
            ->groupBy('category')
            ->sum('amount', 'totalAmount')
            ->calculate('bonus', function (array $groupState) {
                // $groupState already contains the aggregated values
                return ($groupState['totalAmount'] ?? 0) + 100;
            })
            ->build();

        $this->assertNotEmpty($report->getGroupBuilders(), 'Group builders should be set on the report');

        // ------------------------------------------------------------------
        // 3. Render with JsonRenderer (makes the structure easy to assert)
        // ------------------------------------------------------------------
        $report->render();

        $output = $jsonRenderer->getOutput();

//        echo "\n--- GENERATED JSON ---\n";
//        echo $output;
//        echo "\n--- END HTML ---\n\n";

        $data   = json_decode($output, true);

        // ------------------------------------------------------------------
        // 4. Assertions
        // ------------------------------------------------------------------
        $this->assertIsArray($data);
        $this->assertArrayHasKey('groups', $data);
        $this->assertCount(2, $data['groups']); // two categories: A and B

        // Helper to find a group by its value
        $findGroup = function (string $value) use ($data) {
            foreach ($data['groups'] as $g) {
                if ($g['value'] === $value) {
                    return $g;
                }
            }
            return null;
        };

        // Category A
        $groupA = $findGroup('A');
        $this->assertNotNull($groupA, 'Group A should exist');
        $this->assertEquals(30, $groupA['aggregates']['totalAmount'] ?? null);
        $this->assertEquals(130, $groupA['aggregates']['bonus'] ?? null);

        // Category B
        $groupB = $findGroup('B');
        $this->assertNotNull($groupB, 'Group B should exist');
        $this->assertEquals(70, $groupB['aggregates']['totalAmount'] ?? null);
        $this->assertEquals(170, $groupB['aggregates']['bonus'] ?? null);
    }
}
