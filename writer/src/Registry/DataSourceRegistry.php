<?php

declare(strict_types=1);

namespace foreup\Reporting\Registry;

use foreup\Reporting\Interfaces\ReportDataSourceInterface;

class DataSourceRegistry
{
    /** @var array<string, ReportDataSourceInterface> */
    private array $sources = [];

    public function register(string $name, ReportDataSourceInterface $source): void
    {
        $this->sources[$name] = $source;
    }

    public function get(string $name): ReportDataSourceInterface
    {
        if (!isset($this->sources[$name])) {
            throw new \InvalidArgumentException("Unknown data source: '{$name}'");
        }
        return $this->sources[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->sources[$name]);
    }
}
