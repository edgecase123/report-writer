<?php

declare(strict_types=1);

namespace ReportWrite\Tests\Unit\Report\AbstractReport;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use ReportWriter\Report\Builder\GroupBuilder;
use ReportWriter\Report\Builder\ReportBuilder;
use ReportWriter\Report\Data\DataProviderInterface;
use ReportWriter\Tests\Unit\Report\AbstractTestReport;

class SingleLevelGroupingTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    const SAMPLE_DATA = [
        ['id' => 1, 'category' => 'A', 'amount' => 100],
        ['id' => 2, 'category' => 'A', 'amount' => 200],
        ['id' => 3, 'category' => 'B', 'amount' => 300],
    ];

    public function test_renders_single_level_grouping_with_correct_band_order_and_context(): void
    {
        $iterator = new \ArrayIterator(self::SAMPLE_DATA);

        $dataProvider = \Mockery::mock(DataProviderInterface::class);
        $dataProvider->shouldReceive('getRecords')
            ->once()
            ->andReturn($iterator);

        // Create a GroupBuilder that groups by 'category'
        $groupBuilder = (new GroupBuilder('category'))
            ->sum(
                'sumAmount',
                'amount'
            );

        $report = new AbstractTestReport();

        $report
            ->setDataProvider($dataProvider)
            ->setGroups([$groupBuilder]);

        $report->render();
        $rendered = $report->getRenderedBands();
        $bandNames = array_map(fn($call) => $call['name'], $rendered);

        // Band order — this should still pass
        $this->assertEquals([
            'reportHeader',
            'groupHeader_0',
            'detail',
            'detail',
            'groupFooter_0',
            'groupHeader_0',
            'detail',
            'groupFooter_0',
            'summary',
            'reportFooter',
        ], $bandNames);

        // Group A header (index 1)
        $this->assertEquals('A', $rendered[1]['context']['firstRecord']['category']);
        $this->assertEquals(0, $rendered[1]['context']['recordCount']);

        // Group A footer (index 4)
        $this->assertEquals('A', $rendered[4]['context']['firstRecord']['category']);
        $this->assertEquals('A', $rendered[4]['context']['lastRecord']['category']);
        $this->assertEquals(2, $rendered[4]['context']['recordCount']);

        // Group B header (index 5)
        $this->assertEquals('B', $rendered[5]['context']['firstRecord']['category']);
        $this->assertEquals(0, $rendered[5]['context']['recordCount']);

        // Group B footer (index 7)
        $this->assertEquals('B', $rendered[7]['context']['firstRecord']['category']);
        $this->assertEquals('B', $rendered[7]['context']['lastRecord']['category']);
        $this->assertEquals(1, $rendered[7]['context']['recordCount']);
    }

    public function test_render_includes_sum_aggregate_in_group_footer(): void
    {
        $iterator = new \ArrayIterator(self::SAMPLE_DATA);

        $dataProvider = \Mockery::mock(DataProviderInterface::class);
        $dataProvider->shouldReceive('getRecords')->once()->andReturn($iterator);

        // ── Use the actual ReportBuilder fluent API ──
        $report = new AbstractTestReport();
        $builder = new ReportBuilder($report);

        $builder
            ->groupBy('category')
            ->sum('amount', 'sumAmount');

        $configuredReport = $builder->build();
        $configuredReport->setDataProvider($dataProvider);
        $configuredReport->render();

        $rendered = $report->getRenderedBands();

        // Find group footers (assuming the same indices as before)
        $groupAFooter = $rendered[4]['context'] ?? [];
        $groupBFooter = $rendered[7]['context'] ?? [];

        $this->assertArrayHasKey('sumAmount', $groupAFooter, "Group A footer missing sumAmount");
        $this->assertArrayHasKey('sumAmount', $groupBFooter, "Group B footer missing sumAmount");

        $this->assertEquals(300.0, $groupAFooter['sumAmount'], "Group A sum incorrect");
        $this->assertEquals(300.0, $groupBFooter['sumAmount'], "Group B sum incorrect");
    }

    public function test_all_aggregate_types_are_calculated_correctly_in_group_footers(): void
    {
        // --------------------------------------------------------------------
        // Prepare data – 5 records in two categories with varying amounts
        // --------------------------------------------------------------------
        $records = [
            ['id' => 1, 'category' => 'Electronics', 'amount' => 100, 'quantity' => 2],
            ['id' => 2, 'category' => 'Electronics', 'amount' => 200, 'quantity' => 1],
            ['id' => 3, 'category' => 'Electronics', 'amount' => 300, 'quantity' => 5],
            ['id' => 4, 'category' => 'Books',        'amount' => 50,  'quantity' => 10],
            ['id' => 5, 'category' => 'Books',        'amount' => 30,  'quantity' => 8],
        ];

        $iterator = new \ArrayIterator($records);
        $dataProvider = \Mockery::mock(DataProviderInterface::class);

        $dataProvider->shouldReceive('getRecords')
            ->once()
            ->andReturn($iterator);

        $report = new AbstractTestReport();

        $report
            ->setDataProvider($dataProvider)
            ->setGroups([
                (new \ReportWriter\Report\Builder\GroupBuilder('category'))
                    ->sum('amount', 'totalAmount')
                    ->avg('amount', 'avgAmount')
                    ->count('amount', 'itemCount')    // counting non-null amounts = number of records
                    ->min('amount', 'minAmount')
                    ->max('amount', 'maxAmount')
                    // We also count a different field to prove field choice matters
                    ->count('quantity', 'quantityCount')
            ]);

        $report->render();

        // Extract band for assertions
        $bands = $report->getRenderedBands();

        // Collect all group footers in order
        $groupFooters = [];
        foreach ($bands as $band) {
            if ($band['name'] === 'groupFooter_0') {
                $groupFooters[] = $band['context'];
            }
        }

        $this->assertCount(2, $groupFooters, 'Expected exactly two group footers');

        $electronicsFooter = $groupFooters[0];
        $booksFooter      = $groupFooters[1];

        $this->assertEquals('Electronics', $electronicsFooter['firstRecord']['category']);
        $this->assertEquals(600.0, $electronicsFooter['totalAmount']);

        $this->assertEquals('Books', $booksFooter['firstRecord']['category']);
        $this->assertEquals(80.0, $booksFooter['totalAmount']);

        $this->assertEquals(200.0, $electronicsFooter['avgAmount']);
        $this->assertEquals(3, $electronicsFooter['itemCount']);
        $this->assertEquals(100.0, $electronicsFooter['minAmount']);
        $this->assertEquals(300.0, $electronicsFooter['maxAmount']);

        $this->assertEquals(40.0, $booksFooter['avgAmount']);
        $this->assertEquals(2, $booksFooter['itemCount']);
        $this->assertEquals(30.0, $booksFooter['minAmount']);
        $this->assertEquals(50.0, $booksFooter['maxAmount']);
    }
}
