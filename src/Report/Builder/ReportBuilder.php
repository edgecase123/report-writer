<?php

namespace ReportWriter\Report\Builder;

use Closure;
use ReportWriter\Report\AbstractReport;
use ReportWriter\Report\Builder\Exception\ReportWriterException;

class ReportBuilder
{
    private AbstractReport $report;
    private array $groups = [];

    public function __construct(AbstractReport $report)
    {
        $this->report = $report;
    }

    public function groupBy($expression): self
    {
        $builder = new GroupBuilder($expression);
        $this->groups[] = $builder;

        return $this;
    }

    public function sum($field, string $as): self
    {
        $this->checkGroupCount();
        $last = &$this->groups[count($this->groups) - 1];
        $last->sum($field, $as);

        return $this;
    }

    public function avg($field, string $as): self
    {
        $this->checkGroupCount();
        $last = &$this->groups[count($this->groups) - 1];
        $last->avg($field, $as);

        return $this;
    }

    public function count($field, string $as): self
    {
        $this->checkGroupCount();
        $last = &$this->groups[count($this->groups) - 1];
        $last->count($field, $as);

        return $this;
    }

    public function min($field, string $as): self
    {
        $this->checkGroupCount();
        $last = &$this->groups[count($this->groups) - 1];
        $last->min($field, $as);

        return $this;
    }

    public function max($field, string $as): self
    {
        $this->checkGroupCount();
        $last = &$this->groups[count($this->groups) - 1];
        $last->max($field, $as);

        return $this;
    }

    public function calculate(string $as, Closure $callback): self
    {
        $last = &$this->groups[count($this->groups) - 1];
        $last->calculate($as, $callback);

        return $this;
    }

    public function build(): AbstractReport
    {
        $this->report->setGroups($this->groups);

        return $this->report;
    }

    private function checkGroupCount(): void
    {
        if (empty($this->groups)) {
            throw new ReportWriterException(
                'You must call groupBy() before adding an aggregate function.'
            );
        }
    }
}