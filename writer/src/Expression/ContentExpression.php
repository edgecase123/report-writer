<?php

declare(strict_types=1);

namespace ReportWriter\Expression;

interface ContentExpression
{
    public function evaluate(EvalContext $ctx): string;
}
