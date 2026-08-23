<?php

declare(strict_types=1);

namespace ReportWriter\Layout;

class PageConfig
{
    public const DEFAULT_WIDTH         = 612.0;  // US Letter points (8.5in × 72)
    public const DEFAULT_HEIGHT        = 792.0;  // US Letter points (11in × 72)
    public const DEFAULT_MARGIN_TOP    = 20.0;
    public const DEFAULT_MARGIN_BOTTOM = 20.0;
    public const DEFAULT_MARGIN_LEFT   = 20.0;
    public const DEFAULT_MARGIN_RIGHT  = 20.0;

    private float $width;
    private float $height;
    private float $marginTop;
    private float $marginBottom;
    private float $marginLeft;
    private float $marginRight;

    public function __construct(
        float $width        = self::DEFAULT_WIDTH,
        float $height       = self::DEFAULT_HEIGHT,
        float $marginTop    = self::DEFAULT_MARGIN_TOP,
        float $marginBottom = self::DEFAULT_MARGIN_BOTTOM,
        float $marginLeft   = self::DEFAULT_MARGIN_LEFT,
        float $marginRight  = self::DEFAULT_MARGIN_RIGHT
    ) {
        $this->width        = $width;
        $this->height       = $height;
        $this->marginTop    = $marginTop;
        $this->marginBottom = $marginBottom;
        $this->marginLeft   = $marginLeft;
        $this->marginRight  = $marginRight;
    }

    public function getWidth(): float { return $this->width; }
    public function getHeight(): float { return $this->height; }
    public function getMarginTop(): float { return $this->marginTop; }
    public function getMarginBottom(): float { return $this->marginBottom; }
    public function getMarginLeft(): float { return $this->marginLeft; }
    public function getMarginRight(): float { return $this->marginRight; }

    public function printableHeight(): float
    {
        return $this->height - $this->marginTop - $this->marginBottom;
    }

    public function printableWidth(): float
    {
        return $this->width - $this->marginLeft - $this->marginRight;
    }
}
