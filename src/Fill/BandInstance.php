<?php

declare(strict_types=1);

namespace ReportWriter\Fill;

final class BandInstance
{
    public function __construct(
        private string $instanceId,
        private string $bandId,
        private string $bandType,
        private array $elementInstances,
    ) {
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