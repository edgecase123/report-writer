<?php

declare(strict_types=1);

namespace foreup\Reporting\Expression;

final class AggregateExpression implements ContentExpression
{
    private string $fn;
    private string $field;

    /** @var callable|null */
    private $formatter;

    public function __construct(string $fn, string $field, ?callable $formatter = null)
    {
        $this->fn        = $fn;
        $this->field     = $field;
        $this->formatter = $formatter;
    }

    public function withFormatter(callable $formatter): self
    {
        $clone            = clone $this;
        $clone->formatter = $formatter;
        return $clone;
    }

    public function getFn(): string { return $this->fn; }

    public function evaluate(EvalContext $ctx): string
    {
        $value = $this->compute($ctx->aggregateRows);
        if ($this->formatter !== null) {
            return (string) ($this->formatter)($value);
        }
        return (string) $value;
    }

    private function compute(array $rows): float
    {
        return AggregateFunction::apply($this->fn, $rows, $this->field);
    }
}
