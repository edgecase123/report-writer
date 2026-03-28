<?php

declare(strict_types=1);

namespace ReportWriter\Fill;

final class ElementInstance
{
    private string $instanceId;
    private string $elementId;
    private string $kind;
    private int $x;
    private int $y;
    private int $width;
    private int $height;
    private ResolvedContent $content;

    public function __construct(
        string $instanceId,
        string $elementId,
        string $kind,
        int $x,
        int $y,
        int $width,
        int $height,
        ResolvedContent $content,
    ) {
        $this->content = $content;
        $this->height = $height;
        $this->width = $width;
        $this->y = $y;
        $this->x = $x;
        $this->kind = $kind;
        $this->elementId = $elementId;
        $this->instanceId = $instanceId;
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function getElementId(): string
    {
        return $this->elementId;
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

    public function getContent(): ResolvedContent
    {
        return $this->content;
    }
}