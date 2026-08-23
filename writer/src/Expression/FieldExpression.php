<?php

declare(strict_types=1);

namespace ReportWriter\Expression;

final class FieldExpression extends AbstractFormattableExpression
{
    private string $field;

    public function __construct(string $field, ?callable $formatter = null)
    {
        $this->field     = $field;
        $this->formatter = $formatter;
    }

    public function evaluate(EvalContext $ctx): string
    {
        $value = $ctx->row[$this->field] ?? '';
        return $this->applyFormatter($value);
    }
}
