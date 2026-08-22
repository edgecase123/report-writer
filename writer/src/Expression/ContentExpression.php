<?php

declare(strict_types=1);

namespace foreup\Reporting\Expression;

interface ContentExpression
{
    public function evaluate(EvalContext $ctx): string;
}
