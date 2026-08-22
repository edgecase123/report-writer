<?php

declare(strict_types=1);

namespace ReportWriter\Instance;

/**
 * Row grouping helper shared by ReportBuilder and DefinitionFiller.
 *
 * Groups a flat row list by the value at $field. Rows whose $field is missing
 * or empty land under the 'Uncategorized' key.
 */
final class Grouping
{
    /**
     * @param  array<int, array<string, mixed>> $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function byField(array $rows, string $field): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = (isset($row[$field]) && $row[$field] !== '') ? (string) $row[$field] : 'Uncategorized';
            $groups[$key][] = $row;
        }
        return $groups;
    }
}
