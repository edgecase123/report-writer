<?php

declare(strict_types=1);

namespace ReportWriter\Renderer;

use ReportWriter\Stream\ReportStream;

interface RendererInterface
{
    public function render(ReportStream $stream): string;

    public function contentType(): string;
}
