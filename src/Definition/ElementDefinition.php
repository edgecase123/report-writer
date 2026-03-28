<?php

declare(strict_types=1);

namespace ReportWriter\Definition;

final class ElementDefinition
{
    public function __construct(
        private string $id,
        private string $kind,
        private int $x,
        private int $y,
        private int $width,
        private int $height,
        private string $expression
    ) {

    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getExpression(): string
    {
        return $this->expression;
    }
}