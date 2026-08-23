<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports\DataSource;

use InvalidArgumentException;

final class DateParam
{
    /** @param array<string, mixed> $params */
    public static function require(array $params, string $name = 'date'): string
    {
        $value = $params[$name] ?? null;
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf("Parameter '%s' must be YYYY-MM-DD.", $name));
        }
        return $value;
    }
}
