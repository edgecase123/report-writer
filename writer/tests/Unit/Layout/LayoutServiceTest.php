<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Layout;

use ReportWriter\Builder\Column;
use ReportWriter\Builder\ReportBuilder;
use ReportWriter\Exceptions\ElementExceedsPageException;
use ReportWriter\Instance\BandInstance;
use ReportWriter\Instance\Content\TextContent;
use ReportWriter\Instance\ElementInstance;
use ReportWriter\Instance\ReportInstance;
use ReportWriter\Layout\Flattener;
use ReportWriter\Layout\LayoutService;
use ReportWriter\Layout\PageConfig;
use PHPUnit\Framework\TestCase;

class LayoutServiceTest extends TestCase
{
    private function makeService(float $pageHeight = 842.0, float $marginTop = 20.0, float $marginBottom = 20.0): LayoutService
    {
        return new LayoutService(
            new Flattener(),
            new PageConfig(595.0, $pageHeight, $marginTop, $marginBottom)
        );
    }

    private function makeReport(array $elements): ReportInstance
    {
        $bands = [];
        foreach ($elements as $i => $el) {
            $bands[] = new BandInstance('b' . $i, 'detail', [$el]);
        }
        return new ReportInstance('root_1', $bands);
    }

    public function testSpecCanonicalExampleSinglePage(): void
    {
        // Spec §3.3: e_title at y=0+margin, li1 at y=30+margin (heights 20+10 gap... but spec shows y=30)
        // The spec example uses margin=0 conceptually; we use marginTop=0 here to match exact values.
        $service = $this->makeService(842.0, 0.0, 0.0);

        $title = new ElementInstance('e_title', 0, 0, 500, 20, new TextContent('Invoice Title'));
        $li1   = new ElementInstance('li1',     0, 0, 500, 20, new TextContent('Line item 1'));

        $report = new ReportInstance(
            'root_1',
            [
                new BandInstance('b1', 'header', [$title]),
                new BandInstance('b2', 'detail', [$li1]),
            ]
        );

        $stream = $service->layout($report);
        $pages  = $stream->getPages();

        $this->assertCount(1, $pages);
        $elements = $pages[0]->getElements();
        $this->assertCount(2, $elements);
        $this->assertSame('e_title', $elements[0]->getInstanceId());
        $this->assertSame(0.0,  $elements[0]->getY());
        $this->assertSame('li1', $elements[1]->getInstanceId());
        $this->assertSame(20.0, $elements[1]->getY());
    }

    public function testOverflowStartsNewPage(): void
    {
        // Page usable height = 50 - 0 - 0 = 50; el1=30, el2=30 → el2 overflows
        $service = $this->makeService(50.0, 0.0, 0.0);

        $el1 = new ElementInstance('el1', 0, 0, 500, 30, new TextContent('A'));
        $el2 = new ElementInstance('el2', 0, 0, 500, 30, new TextContent('B'));

        $stream = $service->layout($this->makeReport([$el1, $el2]));
        $pages  = $stream->getPages();

        $this->assertCount(2, $pages);
        $this->assertSame('el1', $pages[0]->getElements()[0]->getInstanceId());
        $this->assertSame('el2', $pages[1]->getElements()[0]->getInstanceId());
        $this->assertSame(0.0,  $pages[1]->getElements()[0]->getY());
    }

    public function testSplittableTextSpansPages(): void
    {
        // Page usable = 30; text has lineHeight=10 so 3 lines fit per page
        // Content has 4 lines → split: 3 on p1, 1 on p2
        $service = $this->makeService(30.0, 0.0, 0.0);
        $content = new TextContent("L1\nL2\nL3\nL4", 10.0);
        $el      = new ElementInstance('el1', 0, 0, 500, 40, $content);

        $stream = $service->layout($this->makeReport([$el]));
        $pages  = $stream->getPages();

        $this->assertCount(2, $pages);
        $this->assertCount(1, $pages[0]->getElements());
        $this->assertCount(1, $pages[1]->getElements());
        $this->assertSame(30.0, $pages[0]->getElements()[0]->getHeight());
        $this->assertSame(10.0, $pages[1]->getElements()[0]->getHeight());
    }

    public function testNonSplittableThrowsWhenLargerThanPage(): void
    {
        $this->expectException(ElementExceedsPageException::class);

        $service = $this->makeService(10.0, 0.0, 0.0);

        $nonSplittable = new class extends \ReportWriter\Instance\Content\ElementContent {
            public function getType(): string { return 'image'; }
            public function isSplittable(): bool { return false; }
            public function toArray(): array { return ['type' => 'image']; }
        };

        $el = new ElementInstance('el1', 0, 0, 500, 50, $nonSplittable);

        $service->layout($this->makeReport([$el]));
    }

    public function testMultipleElementsFitExactly(): void
    {
        $service = $this->makeService(60.0, 0.0, 0.0);

        $elements = [
            new ElementInstance('el1', 0, 0, 500, 20, new TextContent('A')),
            new ElementInstance('el2', 0, 0, 500, 20, new TextContent('B')),
            new ElementInstance('el3', 0, 0, 500, 20, new TextContent('C')),
        ];

        $stream = $service->layout($this->makeReport($elements));
        $this->assertCount(1, $stream->getPages());
        $this->assertCount(3, $stream->getPages()[0]->getElements());
    }

    public function testRowSpacingShiftsBandsDown(): void
    {
        $service = $this->makeService(200.0, 0.0, 0.0);

        $el1 = new ElementInstance('el1', 0, 0, 500, 20, new TextContent('A'));
        $el2 = new ElementInstance('el2', 0, 0, 500, 20, new TextContent('B'));

        $band1 = new BandInstance('b1', 'detail', [$el1], null, 10.0);
        $band2 = new BandInstance('b2', 'detail', [$el2]);
        $report = new ReportInstance('r', [$band1, $band2]);

        $stream  = $service->layout($report);
        $placed  = $stream->getPages()[0]->getElements();

        $this->assertSame(0.0,  $placed[0]->getY()); // band1 at cursor 0
        $this->assertSame(30.0, $placed[1]->getY()); // band2 at cursor 0+20+10
    }

    public function testRowSpacingCausesPageBreakWhenSpaceExhausted(): void
    {
        // Page is exactly 30pt usable; band is 20pt + 10pt spacing = 30pt consumed.
        // Second band (20pt) should overflow to page 2.
        $service = $this->makeService(30.0, 0.0, 0.0);

        $el1 = new ElementInstance('el1', 0, 0, 500, 20, new TextContent('A'));
        $el2 = new ElementInstance('el2', 0, 0, 500, 20, new TextContent('B'));

        $band1 = new BandInstance('b1', 'detail', [$el1], null, 10.0);
        $band2 = new BandInstance('b2', 'detail', [$el2]);
        $report = new ReportInstance('r', [$band1, $band2]);

        $stream = $service->layout($report);
        $this->assertCount(2, $stream->getPages());
    }

    public function testWidthZeroElementResolvesToPrintableWidth(): void
    {
        // PageConfig: width=612, marginLeft=20, marginRight=20 → printableWidth=572
        $service = new LayoutService(
            new Flattener(),
            new PageConfig(612.0, 792.0, 20.0, 20.0, 20.0, 20.0)  // printableWidth=572
        );

        $el     = new ElementInstance('sentinel', 0.0, 0.0, 0.0, 20.0, new TextContent('X'));
        $report = new ReportInstance('r1', [new BandInstance('b1', 'title', [$el])]);

        $positioned = $service->layout($report)->getPages()[0]->getElements()[0];

        $this->assertSame(572.0, $positioned->getWidth());
    }

    public function testNonZeroWidthPassesThroughUnchanged(): void
    {
        $service = new LayoutService(
            new Flattener(),
            new PageConfig(612.0, 792.0, 20.0, 20.0, 20.0, 20.0)  // printableWidth=572
        );

        $el     = new ElementInstance('fixed', 0.0, 0.0, 300.0, 20.0, new TextContent('X'));
        $report = new ReportInstance('r1', [new BandInstance('b1', 'detail', [$el])]);

        $positioned = $service->layout($report)->getPages()[0]->getElements()[0];

        $this->assertSame(300.0, $positioned->getWidth());
    }

    public function testSplittableWidthZeroResolvesOnBothChunks(): void
    {
        // Page usable = 30; text has lineHeight=10 so 3 lines fit per page.
        // Content is 4 lines → split: 3 on p1, 1 on p2. Both chunks must get resolved width.
        $service = new LayoutService(
            new Flattener(),
            new PageConfig(612.0, 30.0, 0.0, 0.0, 20.0, 20.0)  // printableWidth=572
        );
        $content = new TextContent("L1\nL2\nL3\nL4", 10.0);
        $el      = new ElementInstance('el1', 0.0, 0.0, 0.0, 40.0, $content);

        $stream = $service->layout(new ReportInstance('r1', [new BandInstance('b1', 'detail', [$el])]));
        $pages  = $stream->getPages();

        $this->assertCount(2, $pages);
        $this->assertSame(572.0, $pages[0]->getElements()[0]->getWidth(), 'first-chunk width must be resolved');
        $this->assertSame(572.0, $pages[1]->getElements()[0]->getWidth(), 'continuation-chunk width must be resolved');
    }

    public function testReportBuilderNarrowColumnsTitleStretchesToPrintableWidth(): void
    {
        // Regression test for Ticket 017.
        // Build a report whose columns span only 380pt on a Letter page
        // (printable width = 572pt). Assert the title's PositionedElement
        // occupies the full printable area — width=572, x=20 (marginLeft).
        $service = new LayoutService(
            new Flattener(),
            new PageConfig(612.0, 792.0, 20.0, 20.0, 20.0, 20.0)  // printableWidth=572
        );

        $report = ReportBuilder::create('narrow_report')
            ->title('Narrow')
            ->columns([
                Column::make('a', 'A', 0.0,   120.0),
                Column::make('b', 'B', 130.0, 120.0),
                Column::make('c', 'C', 260.0, 120.0),
            ])
            ->build();

        $positioned = $service->layout($report)->getPages()[0]->getElements();

        // The first PositionedElement in the stream is the title element
        // (title band placed at the top of the first page).
        $this->assertSame('title', $positioned[0]->getInstanceId());
        $this->assertSame(20.0,    $positioned[0]->getX(),     'title x must equal marginLeft');
        $this->assertSame(572.0,   $positioned[0]->getWidth(), 'title width must equal printableWidth');
    }
}
