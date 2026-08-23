<?php

declare(strict_types=1);

namespace ReportWriter\Expression;

final class ComputedExpression extends AbstractFormattableExpression
{
    /** @var callable */
    private $fn;

    public function __construct(callable $fn, ?callable $formatter = null)
    {
        $this->fn        = $fn;
        $this->formatter = $formatter;
    }

    public function evaluate(EvalContext $ctx): string
    {
        $value = ($this->fn)($ctx);
        return $this->applyFormatter($value);
    }
}
