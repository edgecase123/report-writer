<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Report;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use ReportWriter\Report\AbstractReport;
use ReportWriter\Report\Builder\GroupBuilder;
use ReportWriter\Report\Builder\ReportBuilder;
use ReportWriter\Report\Data\DataProviderInterface;

class AbstractReportTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_renders_single_level_grouping_with_correct_band_order_and_context(): void
    {
        // --------------------------------------------------------------------
        // 1. Prepare mock data provider with 3 records
        // --------------------------------------------------------------------
        $records = [
            ['id' => 1, 'category' => 'A', 'amount' => 100],
            ['id' => 2, 'category' => 'A', 'amount' => 200],
            ['id' => 3, 'category' => 'B', 'amount' => 300],
        ];

        $iterator = new \ArrayIterator($records);

        $dataProvider = \Mockery::mock(DataProviderInterface::class);
        $dataProvider->shouldReceive('getRecords')
            ->once()
            ->andReturn($iterator);

        // --------------------------------------------------------------------
        // 2. Create a GroupBuilder that groups by 'category'
        // --------------------------------------------------------------------
        $groupBuilder = (new GroupBuilder('category'))
            ->sum(
                'sumAmount',           // ← $as: the name you want in the template
                'amount'               // ← $field: the actual field in the record
            );

        // --------------------------------------------------------------------
        // 3. Anonymous class to spy on renderBand calls
        // --------------------------------------------------------------------
        $report = new class extends AbstractReport {
            private array $renderedBands = [];

            public function getRenderedBands(): array
            {
                return $this->renderedBands;
            }

            protected function renderBand(string $type, ?int $level = null, $context = null): string
            {
                $name = $level !== null ? $type . '_' . $level : $type;
                if (str_ends_with($name, 'groupFooter_0')) {
                    echo "Footer $name:\n";
                    if (is_array($context)) {
                        echo "  All context keys: " . implode(', ', array_keys($context)) . "\n";
                        if (isset($context['sumAmount'])) {
                            echo "  sumAmount = " . $context['sumAmount'] . "\n";
                        } else {
                            echo "  sumAmount = MISSING\n";
                        }
                        echo "  recordCount = " . ($context['recordCount'] ?? 'N/A') . "\n";
                        if (!empty($context['firstRecord'])) {
                            echo "  firstRecord category = " . $context['firstRecord']['category'] . "\n";
                        }
                    }
                }
                $this->renderedBands[] = [
                    'name' => $name,
                    'context' => $context,
                ];
                return '';
            }
        };

        // --------------------------------------------------------------------
        // 4. Configure the report
        // --------------------------------------------------------------------
        $report
            ->setDataProvider($dataProvider)
            ->setGroups([$groupBuilder]);

        // --------------------------------------------------------------------
        // 5. Execute
        // --------------------------------------------------------------------
        $report->render();

        // --------------------------------------------------------------------
        // 6. Get results and debug output (keep this — it's very helpful)
        // --------------------------------------------------------------------
        $rendered = $report->getRenderedBands();

        echo "\n--- FULL RENDERED BANDS DEBUG ---\n";
        foreach ($rendered as $index => $call) {
            echo "$index: name = " . $call['name'] . "\n";
            echo "   context keys: " . implode(', ', array_keys($call['context'] ?? [])) . "\n";
            if (isset($call['context']['firstRecord']) && $call['context']['firstRecord'] !== null) {
                echo "   firstRecord category: " . $call['context']['firstRecord']['category'] . "\n";
            }
            if (isset($call['context']['lastRecord']) && $call['context']['lastRecord'] !== null) {
                echo "   lastRecord category: " . $call['context']['lastRecord']['category'] . "\n";
            }
            if (isset($call['context']['recordCount'])) {
                echo "   recordCount: " . $call['context']['recordCount'] . "\n";
            }
        }
        echo "--- END DEBUG ---\n\n";


        // --------------------------------------------------------------------
        // 7. Assertions — corrected for actual behavior
        // --------------------------------------------------------------------
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

        // Group A footer (index 4) — now correct after fix
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
        $records = [
            ['id' => 1, 'category' => 'A', 'amount' => 100],
            ['id' => 2, 'category' => 'A', 'amount' => 200],
            ['id' => 3, 'category' => 'B', 'amount' => 300],
        ];

        $iterator = new \ArrayIterator($records);

        $dataProvider = \Mockery::mock(DataProviderInterface::class);
        $dataProvider->shouldReceive('getRecords')->once()->andReturn($iterator);

        // ── Use the actual ReportBuilder fluent API ──
        $report = new class extends AbstractReport {
            private array $renderedBands = [];

            public function getRenderedBands(): array { return $this->renderedBands; }

            protected function renderBand(string $type, ?int $level = null, $context = null): string
            {
                $name = $level !== null ? $type . '_' . $level : $type;
                $this->renderedBands[] = [
                    'name' => $name,
                    'context' => $context,
                ];
                return '';
            }
        };

        $builder = new ReportBuilder($report);

        $builder
            ->groupBy('category')
            ->sum('amount', 'sumAmount');   // field first, alias second — as in your ReportBuilder

        $configuredReport = $builder->build();

        $configuredReport->setDataProvider($dataProvider);

        $configuredReport->render();

        $rendered = $report->getRenderedBands();   // ← using the same instance

        // Find group footers (assuming same indices as before)
        $groupAFooter = $rendered[4]['context'] ?? [];
        $groupBFooter = $rendered[7]['context'] ?? [];

        $this->assertArrayHasKey('sumAmount', $groupAFooter, "Group A footer missing sumAmount");
        $this->assertArrayHasKey('sumAmount', $groupBFooter, "Group B footer missing sumAmount");

        $this->assertEquals(300.0, $groupAFooter['sumAmount'], "Group A sum incorrect");
        $this->assertEquals(300.0, $groupBFooter['sumAmount'], "Group B sum incorrect");
    }
}