<?php

declare(strict_types=1);

namespace ReportWriter\Expression;

final class EvalContext
{
    public array $row;
    public array $aggregateRows;
    public array $params;

    public function __construct(array $row = [], array $aggregateRows = [], array $params = [])
    {
        $this->row           = $row;
        $this->aggregateRows = $aggregateRows;
        $this->params        = $params;
    }
}
