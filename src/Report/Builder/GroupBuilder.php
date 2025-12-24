<?php

namespace ReportWriter\Report\Builder;

use Closure;

class GroupBuilder
{
    /** @var string|Closure */
    private $expression;
    private array $aggregates = [];
    private array $calculations = [];

    /**
     * @param $expression string|Closure
     */
    public function __construct($expression)
    {
        $this->expression = $expression;
    }

    /**
     * @return Closure|string
     */
    public function getExpression()
    {
        return $this->expression;
    }

    public function sum($field, string $as): self
    {
        $this->aggregates[] = ['type' => 'sum', 'field' => $field, 'as' => $as];

        return $this;
    }

    public function avg($field, string $as): self
    {
        $this->aggregates[] = ['type' => 'avg', 'field' => $field, 'as' => $as];

        return $this;
    }

    public function count($field, string $as): self
    {
        $this->aggregates[] = ['type' => 'count', 'field' => $field, 'as' => $as];

        return $this;
    }

    public function min($field, string $as): self
    {
        $this->aggregates[] = ['type' => 'min', 'field' => $field, 'as' => $as];

        return $this;
    }

    public function max($field, string $as): self
    {
        $this->aggregates[] = ['type' => 'max', 'field' => $field, 'as' => $as];

        return $this;
    }

    public function calculate(string $as, Closure $callback): self
    {
        $this->calculations[] = ['as' => $as, 'callback' => $callback];

        return $this;
    }

    public function getAggregates(): array
    {
        return $this->aggregates;
    }

    public function getCalculations(): array
    {
        return $this->calculations;
    }
}
