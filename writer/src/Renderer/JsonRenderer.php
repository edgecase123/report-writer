<?php

declare(strict_types=1);

namespace ReportWriter\Renderer;

use ReportWriter\Stream\ReportStream;

class JsonRenderer implements RendererInterface
{
    public function contentType(): string
    {
        return 'application/json';
    }

    public function render(ReportStream $stream): string
    {
        return json_encode($stream->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
