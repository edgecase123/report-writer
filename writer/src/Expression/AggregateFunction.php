<?php

declare(strict_types=1);

namespace ReportWriter\Expression;

/**
 * Shared aggregate math for sum/avg/min/max/count.
 *
 * Both DefinitionFiller and AggregateExpression delegate here so the switch
 * on function name lives in exactly one place.
 */
final class AggregateFunction
{
    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public static function apply(string $fn, array $rows, string $field): float
    {
        if (empty($rows)) {
            return 0.0;
        }

        $values = array_map(static fn ($row) => (float) ($row[$field] ?? 0), $rows);

        switch ($fn) {
            case 'count': return (float) count($values);
            case 'avg':   return array_sum($values) / count($values);
            case 'min':   return (float) min($values);
            case 'max':   return (float) max($values);
            default:      return (float) array_sum($values); // sum
        }
    }
}
