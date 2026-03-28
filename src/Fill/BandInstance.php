<?php

declare(strict_types=1);

namespace ReportWriter\Fill;

final class BandInstance
{
    private string $instanceId;
    private string $bandId;
    private string $bandType;
    private array $elementInstances;

    public function __construct(
        string $instanceId,
        string $bandId,
        string $bandType,
        array $elementInstances,
    ) {
        $this->elementInstances = $elementInstances;
        $this->bandType = $bandType;
        $this->bandId = $bandId;
        $this->instanceId = $instanceId;
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function getBandId(): string
    {
        return $this->bandId;
    }

    public function getBandType(): string
    {
        return $this->bandType;
    }

    public function getElementInstances(): array
    {
        return $this->elementInstances;
    }
}