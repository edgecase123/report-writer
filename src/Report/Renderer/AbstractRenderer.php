<?php

namespace ReportWriter\Report\Renderer;

use ReportWriter\Report\AbstractReport;

abstract class AbstractRenderer implements RendererInterface
{
    protected string $output = '';
    protected ?AbstractReport $report = null;

    abstract public function renderBand(string $type, ?int $level, $context): string;

    public function getOutput(): string
    {
        return $this->output;
    }

    public function setReport(AbstractReport $report): self
    {
        $this->report = $report;

        return $this;
    }

    // === HELPERS ===

    protected function append(string $data): void
    {
        $this->output .= $data;
    }

    protected function appendLine(string $data = '', int $indent = 0): void
    {
        $this->output .= str_repeat('  ', $indent) . $data . "\n";
    }

    protected function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // === SHARED FORMATTING ===

    protected function formatValue($value, ?string $format): string
    {
        if ($format === null) {
            return $value;
        }

        if (is_callable($format)) {
            return $format($value);
        }

        switch ($format) {
            case 'currency':
                return is_numeric($value)
                    ? '$' . number_format((float)$value, 2)
                    : $value;

            case 'number':
                return is_numeric($value)
                    ? number_format((float)$value)
                    : $value;

            case 'boolean':
                return $value ? 'Yes' : 'No';

            case 'date':
                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d');
                }
                if (is_string($value)) {
                    return date('Y-m-d', strtotime($value)) ?: $value;
                }
                return $value;

            default:
                if (substr($format, 0, 5) === 'date:') {
                    $phpFormat = substr($format, 5);
                    if ($value instanceof \DateTimeInterface) {
                        return $value->format($phpFormat);
                    }
                    if (is_string($value)) {
                        $timestamp = strtotime($value);
                        return $timestamp ? date($phpFormat, $timestamp) : $value;
                    }
                }
                return $value;
        }
    }
}
