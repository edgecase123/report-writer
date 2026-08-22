<?php

declare(strict_types=1);

namespace ReportWriter\Instance;

class ReportInstance
{
    private string $reportInstanceId;
    /** @var BandInstance[] */
    private array $bandInstances;
    /** @var SubreportInstance[] keyed by subreport_instance_id */
    private array $subreportInstances;

    /**
     * @param BandInstance[]      $bandInstances
     * @param SubreportInstance[] $subreportInstances keyed by subreport_instance_id
     */
    public function __construct(
        string $reportInstanceId,
        array $bandInstances,
        array $subreportInstances = []
    ) {
        $this->reportInstanceId   = $reportInstanceId;
        $this->bandInstances      = $bandInstances;
        $this->subreportInstances = $subreportInstances;
    }

    public function getReportInstanceId(): string { return $this->reportInstanceId; }

    /** @return BandInstance[] */
    public function getBandInstances(): array { return $this->bandInstances; }

    /** @return SubreportInstance[] */
    public function getSubreportInstances(): array { return $this->subreportInstances; }

    public function getSubreportInstance(string $id): ?SubreportInstance
    {
        return $this->subreportInstances[$id] ?? null;
    }
}
