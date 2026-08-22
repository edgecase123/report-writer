<?php

declare(strict_types=1);

namespace foreup\Reporting\Instance;

use foreup\Reporting\Instance\Content\ElementContent;

class ElementInstance
{
    private string $instanceId;
    private float $x;
    private float $y;
    private float $width;
    private float $height;
    private ElementContent $content;
    private string $textAlign;

    public function __construct(
        string $instanceId,
        float $x,
        float $y,
        float $width,
        float $height,
        ElementContent $content,
        string $textAlign = ''
    ) {
        $this->instanceId = $instanceId;
        $this->x          = $x;
        $this->y          = $y;
        $this->width      = $width;
        $this->height     = $height;
        $this->content    = $content;
        $this->textAlign  = $textAlign;
    }

    public function getInstanceId(): string      { return $this->instanceId; }
    public function getX(): float                { return $this->x; }
    public function getY(): float                { return $this->y; }
    public function getWidth(): float            { return $this->width; }
    public function getHeight(): float           { return $this->height; }
    public function getContent(): ElementContent { return $this->content; }
    public function getTextAlign(): string       { return $this->textAlign; }

    public function withPosition(float $x, float $y): self
    {
        $clone    = clone $this;
        $clone->x = $x;
        $clone->y = $y;
        return $clone;
    }

    public function withHeight(float $height): self
    {
        $clone         = clone $this;
        $clone->height = $height;
        return $clone;
    }

    public function toArray(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'x'           => $this->x,
            'y'           => $this->y,
            'width'       => $this->width,
            'height'      => $this->height,
            'content'     => $this->content->toArray(),
        ];
    }
}
