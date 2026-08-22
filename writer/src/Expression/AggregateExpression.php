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
        if (empty($rows)) {
            return 0.0;
        }
        $values = array_map(fn($r) => (float) ($r[$this->field] ?? 0), $rows);
        switch ($this->fn) {
            case 'avg':   return array_sum($values) / count($values);
            case 'min':   return (float) min($values);
            case 'max':   return (float) max($values);
            case 'count': return (float) count($values);
            default:      return array_sum($values); // sum
        }
    }
}
