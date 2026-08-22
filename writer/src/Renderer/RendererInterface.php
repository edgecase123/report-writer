<?php

declare(strict_types=1);

namespace foreup\Reporting\Renderer;

use foreup\Reporting\Stream\ReportStream;

interface RendererInterface
{
    public function render(ReportStream $stream): string;

    public function contentType(): string;
}
