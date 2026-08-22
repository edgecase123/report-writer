<?php

declare(strict_types=1);

namespace ReportWriter\Interfaces;

use ReportWriter\Instance\ReportInstance;

interface ReportFillerInterface
{
    public function fill(array $params): ReportInstance;
}
