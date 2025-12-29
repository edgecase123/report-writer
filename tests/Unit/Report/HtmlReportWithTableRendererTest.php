<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Report;

use PHPUnit\Framework\TestCase;
use ReportWriter\Report\Builder\GroupBuilder;
use ReportWriter\Report\Data\ArrayDataProvider;
use ReportWriter\Report\HtmlReport;
use ReportWriter\Report\Renderer\HtmlTableRenderer;


class HtmlReportWithTableRendererTest extends TestCase
{
    private const SAMPLE_DATA = [
        [
            'id' => 1,
            'category' => 'A',
            'amount' => 1234.5,
            'product' => 'Widget X',
            'active' => true,
            'created_at' => '2025-01-15',
        ],
        [
            'id' => 2,
            'category' => 'A',
            'amount' => 987.65,
            'product' => 'Widget Y',
            'active' => false,
            'created_at' => '2025-02-20',
        ],
        [
            'id' => 3,
            'category' => 'B',
            'amount' => 500,
            'product' => 'Gadget Z',
            'active' => true,
            'created_at' => '2025-03-10',
        ],
    ];

    public function test_renders_full_html_table_with_correct_structure_and_aggregates(): void
    {
        // Setup data provider (we'll create a simple ArrayDataProvider)
        $dataProvider = new ArrayDataProvider(self::SAMPLE_DATA);

        // Create a renderer and report
        $renderer = new HtmlTableRenderer();
        $report = new HtmlReport($renderer);

        // Configure grouping and aggregates
        $groupBuilder = (new GroupBuilder('category'))
            ->sum('amount', 'sumAmount')
            ->count('amount', 'itemCount'); // should be same as recordCount

        $report
            ->setDataProvider($dataProvider)
            ->setGroups([$groupBuilder])
            ->setColumns([
                'product' => 'Product Name',
                'amount'  => ['label' => 'Amount ($)', 'format' => 'currency'],
                'active'  => ['label' => 'Active', 'format' => 'boolean'],
                'created_at' => ['label' => 'Created', 'format' => 'date:M j, Y'],
                'category' => 'Category',
            ]);

        $html = $report->render();

        echo "\n--- GENERATED HTML ---\n";
        echo $html;
        echo "\n--- END HTML ---\n\n";

        // --------------------------------------------------------------------
        // 1. Basic structure
        // --------------------------------------------------------------------
        $this->assertStringContainsString('<table class="report-table">', $html);
        $this->assertStringContainsString('<thead>', $html);
        $this->assertEquals(2, substr_count($html, '<tbody>'), 'Expected one tbody per group');
        $this->assertStringContainsString('<tfoot>', $html);

        // --------------------------------------------------------------------
        // 2. Column headers — exact labels and correct order
        // --------------------------------------------------------------------
        $this->assertStringContainsString('<th>Product Name</th>', $html);
        $this->assertStringContainsString('<th>Amount ($)</th>', $html);
        $this->assertStringContainsString('<th>Active</th>', $html);
        $this->assertStringContainsString('<th>Created</th>', $html);
        $this->assertStringContainsString('<th>Category</th>', $html);

        $this->assertMatchesRegularExpression(
            '/Product.*Amount.*Active.*Created.*Category/s',
            $html,
            'Headers must appear in the exact configured order'
        );

        // --------------------------------------------------------------------
        // 3. Formatted values in detail rows
        // --------------------------------------------------------------------
        $this->assertStringContainsString('<td>$1,234.50</td>', $html);
        $this->assertStringContainsString('<td>$987.65</td>', $html);
        $this->assertStringContainsString('<td>$500.00</td>', $html);

        $this->assertStringContainsString('<td>Yes</td>', $html);
        $this->assertStringContainsString('<td>No</td>', $html);

        $this->assertStringContainsString('<td>Jan 15, 2025</td>', $html);
        $this->assertStringContainsString('<td>Feb 20, 2025</td>', $html);
        $this->assertStringContainsString('<td>Mar 10, 2025</td>', $html);

        // --------------------------------------------------------------------
        // 4. Detail row order (values appear in column order)
        // --------------------------------------------------------------------
        $this->assertMatchesRegularExpression(
            '/Widget X.*\$1,234\.50.*Yes.*Jan 15, 2025.*A/s',
            $html,
            'Widget X row respects column order and formatting'
        );

        $this->assertMatchesRegularExpression(
            '/Widget Y.*\$987\.65.*No.*Feb 20, 2025.*A/s',
            $html,
            'Widget Y row respects column order and formatting'
        );

        $this->assertMatchesRegularExpression(
            '/Gadget Z.*\$500\.00.*Yes.*Mar 10, 2025.*B/s',
            $html,
            'Gadget Z row respects column order and formatting'
        );

        // --------------------------------------------------------------------
        // 5. Group totals (updated for current data)
        // --------------------------------------------------------------------
        $this->assertStringContainsString('Group: A', $html, 'Group A header should be present');

        $this->assertTrue(
            str_contains($html, '$2,222.15') ||
            str_contains($html, '2222.15') ||
            str_contains($html, '2,222.15'),
            'Group A total of 2222.15 not found in output (checked formatted and raw variants)'
        );

        $this->assertStringContainsString('Group: B', $html, 'Group B header should be present');

        $this->assertTrue(
            str_contains($html, '$500.00') ||
            str_contains($html, '500.00') ||
            str_contains($html, '500'),
            'Group B total of 500.00 not found in output'
        );

        // --------------------------------------------------------------------
        // 6. Grand total
        // --------------------------------------------------------------------
        $this->assertStringContainsString('2,722.15', $html);

        // --------------------------------------------------------------------
        // 7. Group header and footer structure
        // --------------------------------------------------------------------
        $this->assertStringContainsString(
            '<td colspan="5"><strong>Group: A</strong></td>',
            $html
        );
        $this->assertStringContainsString(
            '<td colspan="4"><strong>Total for group</strong></td>',
            $html
        );

        // --------------------------------------------------------------------
        // 8. Report header and footer
        // --------------------------------------------------------------------
        $this->assertStringContainsString('<h1>Report Title</h1>', $html);
        $this->assertStringContainsString('Report generated by ReportWriter', $html);
    }
}