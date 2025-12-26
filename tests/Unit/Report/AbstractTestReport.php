<?php

namespace ReportWriter\Tests\Unit\Report;

use ReportWriter\Report\AbstractReport;

class AbstractTestReport extends AbstractReport {
    private array $renderedBands = [];

    public function getRenderedBands(): array
    {
        return $this->renderedBands;
    }

    protected function renderBand(string $type, ?int $level = null, $context = null): string
    {
        $name = $level !== null ? $type . '_' . $level : $type;

        $this->renderedBands[] = [
            'name' => $name,
            'context' => $context,
        ];

        return '';
    }
}
