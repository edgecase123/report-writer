<?php

declare(strict_types=1);

namespace foreup\Reporting;

use foreup\Reporting\Interfaces\ReportFillerInterface;
use foreup\Reporting\Layout\LayoutService;
use foreup\Reporting\Stream\ReportStream;

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
