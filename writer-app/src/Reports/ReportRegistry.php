<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports;

use OutOfBoundsException;

final class ReportRegistry
{
    /** @var array<string, ReportDefinition> */
    private array $byId = [];

    /** @var ReportDefinition[] */
    private array $ordered = [];

    /**
     * @param ReportDefinition[] $definitions
     */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $def) {
            $this->byId[$def->getId()] = $def;
            $this->ordered[]           = $def;
        }
    }

    public function get(string $id): ReportDefinition
    {
        if (!isset($this->byId[$id])) {
            throw new OutOfBoundsException("Unknown report '{$id}'");
        }
        return $this->byId[$id];
    }

    /** @return ReportDefinition[] */
    public function all(): array
    {
        return $this->ordered;
    }
}
