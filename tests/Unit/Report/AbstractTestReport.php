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
        if (str_ends_with($name, 'groupFooter_0')) {
            echo "Footer $name:\n";
            if (is_array($context)) {
                echo "  All context keys: " . implode(', ', array_keys($context)) . "\n";
                if (isset($context['sumAmount'])) {
                    echo "  sumAmount = " . $context['sumAmount'] . "\n";
                } else {
                    echo "  sumAmount = MISSING\n";
                }
                echo "  recordCount = " . ($context['recordCount'] ?? 'N/A') . "\n";
                if (!empty($context['firstRecord'])) {
                    echo "  firstRecord category = " . $context['firstRecord']['category'] . "\n";
                }
            }
        }
        $this->renderedBands[] = [
            'name' => $name,
            'context' => $context,
        ];
        return '';
    }
}
