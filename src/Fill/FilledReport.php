<?php

declare(strict_types=1);

namespace ReportWriter\Fill;

final class FilledReport
{
    public function __construct(
        private string $reportDefinitionId,
        private array $bandInstances,
    ) {
    }

    public function getReportDefinitionId(): string
    {
        return $this->reportDefinitionId;
    }

    public function getBandInstances(): array
    {
        return $this->bandInstances;
    }
}