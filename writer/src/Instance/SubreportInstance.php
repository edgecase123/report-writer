<?php

declare(strict_types=1);

namespace foreup\Reporting\Instance;

class SubreportInstance
{
    private string $subreportInstanceId;
    private string $reportDefinitionId;
    /** @var BandInstance[] */
    private array $bandInstances;

    /** @param BandInstance[] $bandInstances */
    public function __construct(
        string $subreportInstanceId,
        string $reportDefinitionId,
        array $bandInstances
    ) {
        $this->subreportInstanceId = $subreportInstanceId;
        $this->reportDefinitionId  = $reportDefinitionId;
        $this->bandInstances       = $bandInstances;
    }

    public function getSubreportInstanceId(): string { return $this->subreportInstanceId; }
    public function getReportDefinitionId(): string { return $this->reportDefinitionId; }

    /** @return BandInstance[] */
    public function getBandInstances(): array { return $this->bandInstances; }
}
