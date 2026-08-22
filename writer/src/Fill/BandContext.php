<?php

declare(strict_types=1);

namespace foreup\Reporting\Fill;

class BandContext
{
    private array $row;
    private ?string $groupValue;
    private array $aggregateRows;
    private array $params;

    public function __construct(
        array $row,
        ?string $groupValue,
        array $aggregateRows,
        array $params
    ) {
        $this->row           = $row;
        $this->groupValue    = $groupValue;
        $this->aggregateRows = $aggregateRows;
        $this->params        = $params;
    }

    public function getRow(): array           { return $this->row; }
    public function getGroupValue(): ?string  { return $this->groupValue; }
    public function getAggregateRows(): array { return $this->aggregateRows; }
    public function getParams(): array        { return $this->params; }
}
