<?php

declare(strict_types=1);

namespace foreup\Reporting\Tests\Unit\Renderer;

use foreup\Reporting\Instance\BandInstance;
use foreup\Reporting\Instance\Content\TextContent;
use foreup\Reporting\Instance\ElementInstance;
use foreup\Reporting\Instance\ReportInstance;
use foreup\Reporting\Interfaces\ReportFillerInterface;
use foreup\Reporting\Layout\Flattener;
use foreup\Reporting\Layout\LayoutService;
use foreup\Reporting\Layout\PageConfig;
use foreup\Reporting\Renderer\JsonRenderer;
use foreup\Reporting\ReportingPipeline;
use PHPUnit\Framework\TestCase;

class JsonRendererTest extends TestCase
{
    private function makePipeline(): ReportingPipeline
    {
        $config = new PageConfig(612.0, 792.0, 20.0, 20.0, 20.0);
        return new ReportingPipeline(new LayoutService(new Flattener(), $config));
    }

    public function testContentType(): void
    {
        $this->assertSame('application/json', (new JsonRenderer())->contentType());
    }

    public function testRendersValidJson(): void
    {
        $pipeline = $this->makePipeline();

        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                return new ReportInstance('r1', [
                    new BandInstance('b1', 'detail', [
                        new ElementInstance('el1', 0, 0, 200, 20, new TextContent('Hello')),
                    ]),
                ]);
            }
        };

        $stream = $pipeline->run($filler);
        $json   = (new JsonRenderer())->render($stream);

        $this->assertJson($json);
    }

    public function testOutputMatchesToArray(): void
    {
        $pipeline = $this->makePipeline();

        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                return new ReportInstance('r1', [
                    new BandInstance('b1', 'detail', [
                        new ElementInstance('el1', 0, 0, 200, 20, new TextContent('Hello')),
                    ]),
                ]);
            }
        };

        $stream = $pipeline->run($filler);

        $this->assertSame(
            json_encode($stream->toArray(), JSON_UNESCAPED_UNICODE),
            (new JsonRenderer())->render($stream)
        );
    }

    public function testMarginsReflectedInOutput(): void
    {
        $pipeline = $this->makePipeline();

        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                return new ReportInstance('r1', [
                    new BandInstance('b1', 'detail', [
                        new ElementInstance('el1', 0, 0, 100, 15, new TextContent('Row')),
                    ]),
                ]);
            }
        };

        $stream = $pipeline->run($filler);
        $data   = json_decode((new JsonRenderer())->render($stream), true);

        $el = $data['pages'][0]['elements'][0];
        $this->assertEquals(20.0, $el['x']);  // marginLeft applied
        $this->assertEquals(20.0, $el['y']);  // marginTop applied
    }

    public function testSpecialCharactersNotEscapedInJson(): void
    {
        $pipeline = $this->makePipeline();

        $filler = new class implements ReportFillerInterface {
            public function fill(array $params): ReportInstance
            {
                return new ReportInstance('r1', [
                    new BandInstance('b1', 'detail', [
                        new ElementInstance('el1', 0, 0, 200, 15, new TextContent('Café & Bar')),
                    ]),
                ]);
            }
        };

        $stream = $pipeline->run($filler);
        $json   = (new JsonRenderer())->render($stream);

        $this->assertStringContainsString('Café & Bar', $json);
    }
}
