<?php

declare(strict_types=1);

namespace ReportWriter\Fill;

final class FilledReport
{
    private string $reportDefinitionId;
    private array $bandInstances;

    public function __construct(
        string $reportDefinitionId,
        array $bandInstances,
    ) {
        $this->bandInstances = $bandInstances;
        $this->reportDefinitionId = $reportDefinitionId;
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