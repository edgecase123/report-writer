<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Renderer;

use ReportWriter\Instance\BandInstance;
use ReportWriter\Instance\Content\TextContent;
use ReportWriter\Instance\ElementInstance;
use ReportWriter\Instance\ReportInstance;
use ReportWriter\Interfaces\ReportFillerInterface;
use ReportWriter\Layout\Flattener;
use ReportWriter\Layout\LayoutService;
use ReportWriter\Layout\PageConfig;
use ReportWriter\Renderer\HtmlRenderer;
use ReportWriter\ReportingPipeline;
use PHPUnit\Framework\TestCase;

class HtmlRendererTest extends TestCase
{
    private function makeConfig(float $h = 842.0): PageConfig
    {
        return new PageConfig(595.0, $h, 0.0, 0.0, 0.0);
    }

    private function makePipeline(PageConfig $config): ReportingPipeline
    {
        return new ReportingPipeline(new LayoutService(new Flattener(), $config));
    }

    public function testRendersTextContentAtCorrectPosition(): void
    {
        $config   = $this->makeConfig();
        $pipeline = $this->makePipeline($config);

        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                return new ReportInstance('r1', [
                    new BandInstance('b1', 'header', [
                        new ElementInstance('el1', 10, 0, 200, 20, new TextContent('Hello World')),
                    ]),
                ]);
            }
        };

        $stream = $pipeline->run($filler);
        $html   = (new HtmlRenderer($config))->render($stream);

        $this->assertStringContainsString('Hello World', $html);
        $this->assertStringContainsString('left:10.00pt', $html);
        $this->assertStringContainsString('top:0.00pt', $html);
        $this->assertStringContainsString('width:200.00pt', $html);
        $this->assertStringContainsString('height:20.00pt', $html);
    }

    public function testEachPageGetsOwnContainer(): void
    {
        $config   = $this->makeConfig(50.0);
        $pipeline = $this->makePipeline($config);

        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                return new ReportInstance('r1', [
                    new BandInstance('b1', 'detail', [
                        new ElementInstance('el1', 0, 0, 500, 30, new TextContent('Page one')),
                    ]),
                    new BandInstance('b2', 'detail', [
                        new ElementInstance('el2', 0, 0, 500, 30, new TextContent('Page two')),
                    ]),
                ]);
            }
        };

        $stream = $pipeline->run($filler);
        $html   = (new HtmlRenderer($config))->render($stream);

        $this->assertSame(2, substr_count($html, 'class="fu-page"'));
        $this->assertStringContainsString('data-page="1"', $html);
        $this->assertStringContainsString('data-page="2"', $html);
        $this->assertStringContainsString('Page one', $html);
        $this->assertStringContainsString('Page two', $html);
    }

    public function testMultiColumnElementsHaveDifferentLeftPositions(): void
    {
        $config   = $this->makeConfig();
        $pipeline = $this->makePipeline($config);

        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                return new ReportInstance('r1', [
                    new BandInstance('b1', 'detail', [
                        new ElementInstance('col_a', 0,   0, 160, 15, new TextContent('Col A')),
                        new ElementInstance('col_b', 170, 0, 160, 15, new TextContent('Col B')),
                        new ElementInstance('col_c', 340, 0, 160, 15, new TextContent('Col C')),
                    ]),
                ]);
            }
        };

        $stream = $pipeline->run($filler);
        $html   = (new HtmlRenderer($config))->render($stream);

        $this->assertStringContainsString('left:0.00pt', $html);
        $this->assertStringContainsString('left:170.00pt', $html);
        $this->assertStringContainsString('left:340.00pt', $html);
        $this->assertStringContainsString('Col A', $html);
        $this->assertStringContainsString('Col B', $html);
        $this->assertStringContainsString('Col C', $html);
    }

    public function testSpecialCharactersAreEscaped(): void
    {
        $config   = $this->makeConfig();
        $pipeline = $this->makePipeline($config);

        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                return new ReportInstance('r1', [
                    new BandInstance('b1', 'detail', [
                        new ElementInstance('el1', 0, 0, 200, 20, new TextContent('<script>alert(1)</script>')),
                    ]),
                ]);
            }
        };

        $stream = $pipeline->run($filler);
        $html   = (new HtmlRenderer($config))->render($stream);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testBandOverlayEmittedForColHeader(): void
    {
        $config   = $this->makeConfig();
        $pipeline = $this->makePipeline($config);

        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                return new ReportInstance('r1', [
                    new BandInstance('b1', 'col-header', [
                        new ElementInstance('c1', 0,   0, 200, 15, new TextContent('Col A')),
                        new ElementInstance('c2', 204, 0, 200, 15, new TextContent('Col B')),
                    ]),
                ]);
            }
        };

        $stream = $pipeline->run($filler);
        $html   = (new HtmlRenderer($config, \ReportWriter\Renderer\StyleMap::defaults()))->render($stream);

        // The overlay div spans full width despite the gap between elements
        $this->assertStringContainsString('fu-band-overlay', $html);
        $this->assertStringContainsString('border-bottom', $html);
        $this->assertStringContainsString('width:100%', $html);
    }

    public function testPageDimensionsAppearedInContainerStyle(): void
    {
        $config   = new PageConfig(612.0, 792.0, 0.0, 0.0);
        $pipeline = $this->makePipeline($config);

        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                return new ReportInstance('r1', [
                    new BandInstance('b1', 'detail', [
                        new ElementInstance('el1', 0, 0, 100, 10, new TextContent('x')),
                    ]),
                ]);
            }
        };

        $stream = $pipeline->run($filler);
        $html   = (new HtmlRenderer($config))->render($stream);

        $this->assertStringContainsString('width:612.00pt', $html);
        $this->assertStringContainsString('height:792.00pt', $html);
    }
}
