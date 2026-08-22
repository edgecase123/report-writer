<?php

declare(strict_types=1);

namespace ReportWriter\Expression;

final class ComputedExpression implements ContentExpression
{
    /** @var callable */
    private $fn;

    /** @var callable|null */
    private $formatter;

    public function __construct(callable $fn, ?callable $formatter = null)
    {
        $this->fn        = $fn;
        $this->formatter = $formatter;
    }

    public function evaluate(EvalContext $ctx): string
    {
        $value = ($this->fn)($ctx);
        if ($this->formatter !== null) {
            return (string) ($this->formatter)($value);
        }
        return (string) $value;
    }
}
