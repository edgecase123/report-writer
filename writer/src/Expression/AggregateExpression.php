<?php

declare(strict_types=1);

namespace ReportWriter\Expression;

final class AggregateExpression extends AbstractFormattableExpression
{
    private string $fn;
    private string $field;

    public function __construct(string $fn, string $field, ?callable $formatter = null)
    {
        $this->fn        = $fn;
        $this->field     = $field;
        $this->formatter = $formatter;
    }

    public function getFn(): string { return $this->fn; }

    public function evaluate(EvalContext $ctx): string
    {
        $value = $this->compute($ctx->aggregateRows);
        return $this->applyFormatter($value);
    }

    private function compute(array $rows): float
    {
        return AggregateFunction::apply($this->fn, $rows, $this->field);
    }
}
