<?php

declare(strict_types=1);

namespace ReportWriter;

use ReportWriter\Interfaces\ReportFillerInterface;
use ReportWriter\Layout\LayoutService;
use ReportWriter\Stream\ReportStream;

class ReportingPipeline
{
    private LayoutService $layoutService;

    public function __construct(LayoutService $layoutService)
    {
        $this->layoutService = $layoutService;
    }

    public function run(ReportFillerInterface $filler, array $params = []): ReportStream
    {
        $instance = $filler->fill($params);
        return $this->layoutService->layout($instance);
    }
}
