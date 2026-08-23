<?php

declare(strict_types=1);

namespace ReportWriter\Expression;

/**
 * Shared formatter plumbing for expressions that support post-evaluation
 * formatting. Extracted from FieldExpression, AggregateExpression, and
 * ComputedExpression (see Ticket 005).
 *
 * Concrete subclasses implement evaluate(EvalContext), compute their raw
 * value, then return $this->applyFormatter($value) as the final step.
 */
abstract class AbstractFormattableExpression implements ContentExpression
{
    /** @var callable|null */
    protected $formatter;

    public function withFormatter(callable $fn): self
    {
        $clone = clone $this;
        $clone->formatter = $fn;
        return $clone;
    }

    /**
     * @param mixed $value
     */
    protected function applyFormatter($value): string
    {
        if ($this->formatter !== null) {
            return (string) ($this->formatter)($value);
        }
        return (string) $value;
    }
}
