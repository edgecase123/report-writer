<?php

declare(strict_types=1);

namespace foreup\Reporting\Expression;

final class StaticExpression implements ContentExpression
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function evaluate(EvalContext $ctx): string
    {
        return $this->value;
    }
}
