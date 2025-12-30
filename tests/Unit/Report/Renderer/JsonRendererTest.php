<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Report\Renderer;

use PHPUnit\Framework\TestCase;
use ReportWriter\Report\Data\ArrayDataProvider;
use ReportWriter\Report\Renderer\JsonRenderer;
use ReportWriter\Report\Report; // or any concrete AbstractReport subclass you use

class JsonRendererTest extends TestCase
{
    public function testItProducesValidJsonWithColumnsAndRows(): void
    {
        // 1. Prepare some simple data
        $records = [
            ['id' => 1, 'name' => 'Alice', 'amount' => 100.50],
            ['id' => 2, 'name' => 'Bob',   'amount' => 200.00],
            ['id' => 3, 'name' => 'Carol', 'amount' => 150.25],
        ];

        // 2. Create the renderer we want to test
        $renderer = new JsonRenderer();

        // 3. Build a minimal report
        $report = new Report($renderer);

        // Configure columns (so we can assert they appear in the JSON)
        $report->setColumns([
            'id'     => 'ID',
            'name'   => 'Customer Name',
            'amount' => ['label' => 'Amount', 'format' => 'currency'],
        ]);

        // Use the handy ArrayDataProvider that ships with the project (it implements DataProviderInterface)
        $dataProvider = new ArrayDataProvider($records);
        $report->setDataProvider($dataProvider);

        // 4. Render the report (this will call the JsonRenderer for every band)
        $output = $report->render();

//        echo "\n--- GENERATED JSON ---\n";
//        echo $output;
//        echo "\n--- END HTML ---\n\n";

        // 5. Basic sanity checks
        $this->assertIsString($output);
        $this->assertNotEmpty($output);

        // Decode the JSON so we can make detailed assertions
        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertJson($output); // PHPUnit helper – fails if not valid JSON

        // --- Metadata ---
        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('generatedAt', $data['metadata']);

        // Accept both +00:00 and +0000 formats (both are valid ISO-8601)
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}([+-]\d{2}:?\d{2}|Z)$/',
            $data['metadata']['generatedAt']
        );

        // --- Columns ---
        $this->assertArrayHasKey('columns', $data);
        $this->assertCount(3, $data['columns']);

        $expectedColumns = [
            ['field' => 'id',     'label' => 'ID',         'format' => null],
            ['field' => 'name',   'label' => 'Customer Name', 'format' => null],
            ['field' => 'amount', 'label' => 'Amount',     'format' => 'currency'],
        ];

        $this->assertSame($expectedColumns, $data['columns']);

        // --- Rows (no grouping in this test, so rows are directly under "groups") ---
        $this->assertArrayHasKey('groups', $data);
        $this->assertIsArray($data['groups']);
        $this->assertCount(3, $data['groups']); // three detail rows

        // Spot-check the first row – amount should be formatted as currency
        $firstRow = $data['groups'][0];
        $this->assertSame('1', $firstRow['id']);               // string because formatValue returns string for display
        $this->assertSame('Alice', $firstRow['name']);
        $this->assertSame('$100.50', $firstRow['amount']);     // currency format applied

        // --- Summary ---
        $this->assertArrayHasKey('summary', $data);
        $this->assertSame(3, $data['summary']['recordCount']);
        // No aggregates defined yet, so aggregates array should be empty
        $this->assertArrayHasKey('aggregates', $data['summary']);
        $this->assertEmpty($data['summary']['aggregates']);
    }
}