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

final class FillEngineTest extends TestCase
{
    public function testFillEngineEmitsSingleDetailBandInstanceWithResolvedElement(): void
    {
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
}