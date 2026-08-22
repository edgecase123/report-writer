<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Layout;

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
}
