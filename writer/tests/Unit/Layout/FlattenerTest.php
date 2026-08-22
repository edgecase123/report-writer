<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Layout;

use ReportWriter\Exceptions\MissingSubreportException;
use ReportWriter\Exceptions\RecursiveSubreportException;
use ReportWriter\Instance\BandInstance;
use ReportWriter\Instance\Content\SubreportContent;
use ReportWriter\Instance\Content\TextContent;
use ReportWriter\Instance\ElementInstance;
use ReportWriter\Instance\ReportInstance;
use ReportWriter\Instance\SubreportInstance;
use ReportWriter\Layout\Flattener;
use PHPUnit\Framework\TestCase;

class FlattenerTest extends TestCase
{
    private Flattener $flattener;

    protected function setUp(): void
    {
        $this->flattener = new Flattener();
    }

    public function testInlinesSubreportInDocumentOrder(): void
    {
        // Spec §3: header band (e_title) + detail band (e_sub → rep_sub_1 → li1)
        // The subreport band (sb1) replaces the placeholder band (b2) in the flat output.
        $titleElement = new ElementInstance('e_title', 0, 0, 500, 20, new TextContent('Invoice Title'));
        $subElement   = new ElementInstance('e_sub',   0, 0, 500,  1, new SubreportContent('rep_sub_1'));
        $lineItem     = new ElementInstance('li1',     0, 0, 500, 20, new TextContent('Line item 1'));

        $subreport = new SubreportInstance(
            'rep_sub_1',
            'line_items',
            [new BandInstance('sb1', 'detail', [$lineItem])]
        );

        $report = new ReportInstance(
            'root_1',
            [
                new BandInstance('b1', 'header', [$titleElement]),
                new BandInstance('b2', 'detail', [$subElement]),
            ],
            ['rep_sub_1' => $subreport]
        );

        $bands = $this->flattener->flatten($report);

        $this->assertCount(2, $bands);
        $this->assertSame('b1',      $bands[0]->getBandInstanceId());
        $this->assertSame('e_title', $bands[0]->getElements()[0]->getInstanceId());
        $this->assertSame('sb1',     $bands[1]->getBandInstanceId());
        $this->assertSame('li1',     $bands[1]->getElements()[0]->getInstanceId());
    }

    public function testThrowsOnMissingSubreport(): void
    {
        $this->expectException(MissingSubreportException::class);

        $subElement = new ElementInstance('e_sub', 0, 0, 500, 1, new SubreportContent('rep_missing'));
        $report     = new ReportInstance(
            'root_1',
            [new BandInstance('b1', 'detail', [$subElement])],
            []
        );

        $this->flattener->flatten($report);
    }

    public function testThrowsOnRecursiveSubreport(): void
    {
        $this->expectException(RecursiveSubreportException::class);

        $selfRef = new ElementInstance('e_self', 0, 0, 500, 1, new SubreportContent('sub_a'));
        $subA    = new SubreportInstance(
            'sub_a',
            'recursive',
            [new BandInstance('b_a', 'detail', [$selfRef])]
        );

        $report = new ReportInstance(
            'root_1',
            [new BandInstance('b1', 'detail', [
                new ElementInstance('e_entry', 0, 0, 500, 1, new SubreportContent('sub_a'))
            ])],
            ['sub_a' => $subA]
        );

        $this->flattener->flatten($report);
    }

    public function testPreservesOrderingAcrossMultipleBands(): void
    {
        $el1 = new ElementInstance('el1', 0, 0, 500, 10, new TextContent('A'));
        $el2 = new ElementInstance('el2', 0, 0, 500, 10, new TextContent('B'));
        $el3 = new ElementInstance('el3', 0, 0, 500, 10, new TextContent('C'));

        $report = new ReportInstance(
            'root_1',
            [
                new BandInstance('b1', 'header', [$el1]),
                new BandInstance('b2', 'detail', [$el2]),
                new BandInstance('b3', 'footer', [$el3]),
            ]
        );

        $bands = $this->flattener->flatten($report);

        $this->assertCount(3, $bands);
        $this->assertSame(
            ['el1', 'el2', 'el3'],
            array_map(fn(BandInstance $b) => $b->getElements()[0]->getInstanceId(), $bands)
        );
    }
}
