<?php

namespace ReportWriter\Report\Renderer;

abstract class AbstractRenderer implements RendererInterface
{
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
                // If format is a string like 'date:Y-m-d'
                if (str_starts_with($format, 'date:')) {
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