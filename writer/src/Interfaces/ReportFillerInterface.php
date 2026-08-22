<?php

declare(strict_types=1);

namespace foreup\Reporting\Interfaces;

use foreup\Reporting\Instance\ReportInstance;

interface ReportFillerInterface
{
    public function fill(array $params): ReportInstance;
}
