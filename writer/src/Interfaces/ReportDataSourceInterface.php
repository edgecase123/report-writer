<?php

declare(strict_types=1);

namespace foreup\Reporting\Interfaces;

interface ReportDataSourceInterface
{
    /**
     * Fetch flat rows for the report given runtime params.
     * Keys in each row must match the column IDs declared in the template.
     *
     * @param  array<string, mixed>             $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchRows(array $params): array;
}
