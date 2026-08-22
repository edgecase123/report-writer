<?php

declare(strict_types=1);

namespace foreup\Reporting\Tests\Unit;

use foreup\Reporting\Instance\BandInstance;
use foreup\Reporting\Instance\Content\SubreportContent;
use foreup\Reporting\Instance\Content\TextContent;
use foreup\Reporting\Instance\ElementInstance;
use foreup\Reporting\Instance\ReportInstance;
use foreup\Reporting\Instance\SubreportInstance;
use foreup\Reporting\Interfaces\ReportFillerInterface;
use foreup\Reporting\Layout\Flattener;
use foreup\Reporting\Layout\LayoutService;
use foreup\Reporting\Layout\PageConfig;
use foreup\Reporting\ReportingPipeline;
use PHPUnit\Framework\TestCase;

class ReportingPipelineTest extends TestCase
{
    public function testEndToEndMatchesSpecExample(): void
    {
        // Spec §3 canonical example: invoice header + line_items subreport
        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                $titleElement = new ElementInstance('e_title', 0, 0, 500, 20, new TextContent('Invoice'));
                $subElement   = new ElementInstance('e_sub',   0, 0, 500,  1, new SubreportContent('rep_sub_1'));
                $lineItem     = new ElementInstance('li1',     0, 0, 500, 20, new TextContent('Line item 1'));

                $subreport = new SubreportInstance(
                    'rep_sub_1',
                    'line_items',
                    [new BandInstance('sb1', 'detail', [$lineItem])]
                );

                return new ReportInstance(
                    'root_1',
                    [
                        new BandInstance('b1', 'header', [$titleElement]),
                        new BandInstance('b2', 'detail', [$subElement]),
                    ],
                    ['rep_sub_1' => $subreport]
                );
            }
        };

        $pipeline = new ReportingPipeline(
            new LayoutService(new Flattener(), new PageConfig(595.0, 842.0, 0.0, 0.0, 0.0))
        );

        $stream = $pipeline->run($filler);
        $pages  = $stream->getPages();

        $this->assertCount(1, $pages);

        $elements = $pages[0]->getElements();
        $this->assertCount(2, $elements);

        $this->assertSame('e_title', $elements[0]->getInstanceId());
        $this->assertSame(0.0,       $elements[0]->getY());
        $this->assertSame(20.0,      $elements[0]->getHeight());

        $this->assertSame('li1',     $elements[1]->getInstanceId());
        $this->assertSame(20.0,      $elements[1]->getY());
        $this->assertSame(20.0,      $elements[1]->getHeight());
    }

    public function testMultiColumnBandElementsShareSameY(): void
    {
        // A detail band with three column elements at different x positions.
        // All three must land at the same y; cursor must advance by max(element height).
        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                $header = new BandInstance('b_header', 'header', [
                    new ElementInstance('hdr', 0, 0, 500, 20, new TextContent('Header')),
                ]);

                $row = new BandInstance('b_row', 'detail', [
                    new ElementInstance('col_a', 0,   0, 160, 15, new TextContent('A')),
                    new ElementInstance('col_b', 170, 0, 160, 15, new TextContent('B')),
                    new ElementInstance('col_c', 340, 0, 160, 15, new TextContent('C')),
                ]);

                $footer = new BandInstance('b_footer', 'footer', [
                    new ElementInstance('ftr', 0, 0, 500, 10, new TextContent('Footer')),
                ]);

                return new ReportInstance('root_1', [$header, $row, $footer]);
            }
        };

        $pipeline = new ReportingPipeline(
            new LayoutService(new Flattener(), new PageConfig(595.0, 842.0, 0.0, 0.0, 0.0))
        );

        $stream   = $pipeline->run($filler);
        $elements = $stream->getPages()[0]->getElements();

        // 4 elements total: 1 header + 3 columns + 1 footer
        $this->assertCount(5, $elements);

        // Header lands at y=0
        $this->assertSame('hdr', $elements[0]->getInstanceId());
        $this->assertSame(0.0,   $elements[0]->getY());

        // All three column elements share the same y (header height = 20)
        $this->assertSame('col_a', $elements[1]->getInstanceId());
        $this->assertSame('col_b', $elements[2]->getInstanceId());
        $this->assertSame('col_c', $elements[3]->getInstanceId());
        $this->assertSame(20.0, $elements[1]->getY());
        $this->assertSame(20.0, $elements[2]->getY());
        $this->assertSame(20.0, $elements[3]->getY());

        // Columns are at their declared x positions
        $this->assertSame(0.0,   $elements[1]->getX());
        $this->assertSame(170.0, $elements[2]->getX());
        $this->assertSame(340.0, $elements[3]->getX());

        // Footer starts after header (20) + row max-height (15) = 35
        $this->assertSame('ftr', $elements[4]->getInstanceId());
        $this->assertSame(35.0,  $elements[4]->getY());
    }

    public function testToArrayProducesExpectedShape(): void
    {
        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                return new ReportInstance(
                    'root_1',
                    [new BandInstance('b1', 'header', [
                        new ElementInstance('el1', 0, 0, 100, 10, new TextContent('Hello'))
                    ])]
                );
            }
        };

        $pipeline = new ReportingPipeline(
            new LayoutService(new Flattener(), new PageConfig(595.0, 842.0, 0.0, 0.0, 0.0))
        );

        $array = $pipeline->run($filler)->toArray();

        $this->assertArrayHasKey('pages', $array);
        $this->assertCount(1, $array['pages']);
        $this->assertSame(1, $array['pages'][0]['page_number']);
        $this->assertSame('el1', $array['pages'][0]['elements'][0]['instance_id']);
    }
}
