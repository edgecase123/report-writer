<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Fill;

use PHPUnit\Framework\TestCase;
use ReportWriter\Definition\BandDefinition;
use ReportWriter\Definition\ElementDefinition;
use ReportWriter\Definition\ReportDefinition;
use ReportWriter\Fill\BandInstance;
use ReportWriter\Fill\ElementInstance;
use ReportWriter\Fill\FillEngine;
use ReportWriter\Fill\ResolvedContent;
use ReportWriter\Tests\BaseTestFile;

final class FillEngineTest extends BaseTestFile
{
    public function testFillEngineEmitsSingleDetailBandInstanceWithResolvedElement(): void
    {
        $array = $this->loadJsonFixture('/fill/basic-detail-rows.data.json');

        self::assertIsArray($array);
        $reportDefinition = new ReportDefinition(
            'invoice_basic',
            [
                new BandDefinition(
                    'detail',
                    'detail',
                    [
                        new ElementDefinition(
                            'customer_name',
                            'text',
                            0,
                            0,
                            200,
                            20,
                            'customer_name'
                        ),
                    ]
                ),
            ]
        );

        $rows = [
            ['customer_name' => 'Napoleon HIll'],
        ];

        $fillEngine = new FillEngine();
        $filledReport = $fillEngine->fill($reportDefinition, $rows);
        self::assertSame('invoice_basic', $filledReport->getReportDefinitionId());
        self::assertCount(1, $filledReport->getBandInstances());

        /** @var BandInstance $bandInstance */
        $bandInstance = $filledReport->getBandInstances()[0];
        self::assertNotSame('', $bandInstance->getInstanceId());
        self::assertSame('detail', $bandInstance->getBandId());
        self::assertSame('detail', $bandInstance->getBandType());
        self::assertCount(1, $bandInstance->getElementInstances());

        /** @var ElementInstance $elementInstance */
        $elementInstance = $bandInstance->getElementInstances()[0];
        self::assertNotSame('', $elementInstance->getInstanceId());
        self::assertSame('customer_name', $elementInstance->getElementId());
        self::assertSame('text', $elementInstance->getKind());
        self::assertSame(0, $elementInstance->getX());
        self::assertSame(0, $elementInstance->getY());
        self::assertSame(200, $elementInstance->getWidth());
        self::assertSame(20, $elementInstance->getHeight());

        self::assertInstanceOf(ResolvedContent::class, $elementInstance->getContent());
        self::assertSame('text', $elementInstance->getContent()->getContentType());
        self::assertSame('Napoleon HIll', $elementInstance->getContent()->getValue());
    }

    public function testFillEngineEmitsOneDetailBandInstancePerInputRow(): void
    {
        $reportDefinition = $this->loadJsonFixture('fill/basic-detail-rows.report.json');

//        dd($reportDefinition);
        $inputRows = $this->loadJsonFixture('fill/basic-detail-rows.data.json');
        $expected = $this->loadJsonFixture('fill/basic-detail-rows.expected.json');

        $fillEngine = new FillEngine();

        $actual = $fillEngine->fill($reportDefinition, $inputRows);

        $this->assertCount(3, $actual['band_instances']);
        $this->assertSame('detail', $actual['band_instances'][0]['band_id']);
        $this->assertSame('detail', $actual['band_instances'][1]['band_id']);
        $this->assertSame('detail', $actual['band_instances'][2]['band_id']);

        $this->assertSame(0, $actual['band_instances'][0]['source_row_index']);
        $this->assertSame(1, $actual['band_instances'][1]['source_row_index']);
        $this->assertSame(2, $actual['band_instances'][2]['source_row_index']);

        $this->assertSame('Acme Corp', $actual['band_instances'][0]['elements'][0]['content']['value']);
        $this->assertSame('42.00', $actual['band_instances'][0]['elements'][1]['content']['value']);

        $this->assertSame('Globex', $actual['band_instances'][1]['elements'][0]['content']['value']);
        $this->assertSame('99.50', $actual['band_instances'][1]['elements'][1]['content']['value']);

        $this->assertSame('Initech', $actual['band_instances'][2]['elements'][0]['content']['value']);
        $this->assertSame('12.75', $actual['band_instances'][2]['elements'][1]['content']['value']);

        $this->assertEquals($expected, $actual);
    }
}