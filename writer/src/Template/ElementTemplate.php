<?php

declare(strict_types=1);

namespace foreup\Reporting\Template;

class ElementTemplate
{
    private string $id;
    private float $x;
    private float $width;
    private float $height;
    private string $align;
    private ContentDefinition $content;

    public function __construct(
        string $id,
        float $x,
        float $width,
        float $height,
        string $align,
        ContentDefinition $content
    ) {
        $this->id      = $id;
        $this->x       = $x;
        $this->width   = $width;
        $this->height  = $height;
        $this->align   = $align;
        $this->content = $content;
    }

    public static function fromArray(array $data): self
    {
        foreach (['id', 'x', 'width', 'height', 'content'] as $required) {
            if (!isset($data[$required])) {
                throw new \InvalidArgumentException("Element requires '{$required}'");
            }
        }
        return new self(
            (string) $data['id'],
            (float)  $data['x'],
            (float)  $data['width'],
            (float)  $data['height'],
            (string) ($data['align'] ?? ''),
            ContentDefinition::fromArray($data['content'])
        );
    }

    public function getId(): string               { return $this->id; }
    public function getX(): float                 { return $this->x; }
    public function getWidth(): float             { return $this->width; }
    public function getHeight(): float            { return $this->height; }
    public function getAlign(): string            { return $this->align; }
    public function getContent(): ContentDefinition { return $this->content; }
}
