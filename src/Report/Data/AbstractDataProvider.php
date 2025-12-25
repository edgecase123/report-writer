<?php

namespace ReportWriter\Report\Data;

abstract class AbstractDataProvider implements DataProviderInterface
{
    protected array $parameters = [];

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function getParameter(string $name, $default = null): array
    {
        return $this->parameters[$name] ?? $default;
    }

    abstract public function getRecords(): \Iterator;
}