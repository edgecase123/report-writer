<?php

declare(strict_types=1);

namespace foreup\Reporting\Stream;

use foreup\Reporting\Instance\Content\ElementContent;

class PositionedElement
{
    private string $instanceId;
    private float $x;
    private float $y;
    private float $width;
    private float $height;
    private ElementContent $content;
    private string $bandType;
    private string $textAlign;

    public function __construct(
        string $instanceId,
        float $x,
        float $y,
        float $width,
        float $height,
        ElementContent $content,
        string $bandType = '',
        string $textAlign = ''
    ) {
        $this->instanceId = $instanceId;
        $this->x          = $x;
        $this->y          = $y;
        $this->width      = $width;
        $this->height     = $height;
        $this->content    = $content;
        $this->bandType   = $bandType;
        $this->textAlign  = $textAlign;
    }

    public function getInstanceId(): string      { return $this->instanceId; }
    public function getX(): float                { return $this->x; }
    public function getY(): float                { return $this->y; }
    public function getWidth(): float            { return $this->width; }
    public function getHeight(): float           { return $this->height; }
    public function getContent(): ElementContent { return $this->content; }
    public function getBandType(): string        { return $this->bandType; }
    public function getTextAlign(): string       { return $this->textAlign; }

    public function toArray(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'x'           => $this->x,
            'y'           => $this->y,
            'width'       => $this->width,
            'height'      => $this->height,
            'content'     => $this->content->toArray(),
            'band_type'   => $this->bandType,
            'text_align'  => $this->textAlign,
        ];
    }
}
