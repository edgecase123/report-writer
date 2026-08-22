<?php

declare(strict_types=1);

namespace ReportWriter\Instance\Content;

class TextContent extends ElementContent
{
    private string $value;
    private float $lineHeight;

    public function __construct(string $value, float $lineHeight = 12.0)
    {
        $this->value      = $value;
        $this->lineHeight = $lineHeight;
    }

    public function getType(): string { return 'text'; }
    public function getValue(): string { return $this->value; }
    public function getLineHeight(): float { return $this->lineHeight; }
    public function isSplittable(): bool { return true; }

    public function toArray(): array
    {
        return ['type' => $this->getType(), 'value' => $this->value];
    }

    public function split(int $linesForCurrentPage): array
    {
        $lines = explode("\n", $this->value);
        $first = array_slice($lines, 0, $linesForCurrentPage);
        $rest  = array_slice($lines, $linesForCurrentPage);

        return [
            new self(implode("\n", $first), $this->lineHeight),
            new self(implode("\n", $rest),  $this->lineHeight),
        ];
    }
}
