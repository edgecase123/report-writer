<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Layout;

use PHPUnit\Framework\TestCase;
use ReportWriter\Layout\PageConfig;

final class PageConfigTest extends TestCase
{
    public function testDefaultsMatchUsLetter(): void
    {
        $c = new PageConfig();

        $this->assertSame(612.0, $c->getWidth());
        $this->assertSame(792.0, $c->getHeight());
        $this->assertSame(20.0, $c->getMarginTop());
        $this->assertSame(20.0, $c->getMarginBottom());
        $this->assertSame(20.0, $c->getMarginLeft());
        $this->assertSame(20.0, $c->getMarginRight());
    }

    public function testCustomMarginRightIsPreserved(): void
    {
        $c = new PageConfig(
            PageConfig::DEFAULT_WIDTH,
            PageConfig::DEFAULT_HEIGHT,
            PageConfig::DEFAULT_MARGIN_TOP,
            PageConfig::DEFAULT_MARGIN_BOTTOM,
            PageConfig::DEFAULT_MARGIN_LEFT,
            36.0
        );

        $this->assertSame(36.0, $c->getMarginRight());
    }

    public function testPrintableHeightSubtractsTopAndBottomMargins(): void
    {
        $c = new PageConfig(612.0, 792.0, 20.0, 20.0);
        $this->assertSame(752.0, $c->printableHeight());
    }

    public function testPrintableWidthSubtractsLeftAndRightMargins(): void
    {
        $c = new PageConfig();
        $this->assertSame(572.0, $c->printableWidth());
    }

    public function testPrintableWidthWithCustomMargins(): void
    {
        $c = new PageConfig(612.0, 792.0, 20.0, 20.0, 40.0, 30.0);
        $this->assertSame(542.0, $c->printableWidth());
    }
}
