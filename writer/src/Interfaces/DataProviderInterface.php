<?php

declare(strict_types=1);

namespace foreup\Reporting\Interfaces;

interface DataProviderInterface
{
    /**
     * Resolve a named data set for the given parameters.
     * Returns mixed data — callers are responsible for typing the result.
     *
     * @param string $key    Identifies the data set (e.g. 'invoice', 'line_items')
     * @param array  $params Contextual parameters (e.g. ['invoice_id' => 42])
     * @return mixed
     */
    public function resolve(string $key, array $params);
}
