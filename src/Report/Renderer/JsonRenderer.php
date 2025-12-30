<?php

declare(strict_types=1);

namespace ReportWriter\Report\Renderer;

use ReportWriter\Report\AbstractReport;
use DateTimeImmutable;

/**
 * Renderer that outputs the full report as structured JSON.
 *
 * The structure is hierarchical and mirrors the banded report logic:
 * - Top-level metadata
 * - Column definitions (label + optional format)
 * - Nested groups (with header value, aggregates, subgroups, rows)
 * - Grand summary
 */
class JsonRenderer extends AbstractRenderer
{
    private array $result = [
        'metadata' => [],
        'columns'  => [],
        'groups'   => [],
        'summary'  => [],
    ];

    private array $groupStack = [];

    public function setReport(AbstractReport $report): self
    {
        $this->report = $report;

        return $this;
    }

    public function renderBand(string $type, ?int $level, $context): string
    {
        switch ($type) {
            case 'reportHeader':
                $this->renderReportHeader($context);
                break;

            case 'groupHeader':
                $this->renderGroupHeader((int) $level, $context);
                break;

            case 'detail':
                $this->renderDetail($context);
                break;

            case 'groupFooter':
                $this->renderGroupFooter((int) $level, $context);
                break;

            case 'summary':
                $this->renderSummary($context);
                break;

            case 'reportFooter':
                // Nothing specific needed for JSON
                break;
        }

        return '';
    }

    public function getOutput(): string
    {
        return json_encode(
                $this->result,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ) . "\n";
    }

    private function renderReportHeader(array $context): void
    {
        $this->result['metadata'] = [
            'generatedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            // You can extend this with title, parameters, etc.
        ];

        // Build column definitions once
        if ($this->report && $this->report->hasConfiguredColumns()) {
            foreach ($this->report->getColumnOrder() as $field) {
                $this->result['columns'][] = [
                    'field'  => $field,
                    'label'  => $this->report->getColumnLabel($field),
                    'format' => $this->report->getColumnFormat($field),
                ];
            }
        }
    }

    private function renderGroupHeader(int $level, array $context): void
    {
        $groupNode = [
            'level'      => $level,
            'value'      => $context['groupValue'] ?? null,
            'aggregates' => [],
            'rows'       => [],
        ];

        // Extract aggregates / calculations for this group
        foreach ($context as $key => $value) {
            if ($key !== 'groupValue' && $key !== 'firstRecord' && $key !== 'lastRecord' && $key !== 'recordCount') {
                $groupNode['aggregates'][$key] = $value;
            }
        }

        // Nested placement
        if ($level === 0) {
            $this->result['groups'][] = &$groupNode;
        } else {
            // Find parent group (previous level on stack)
            $parent =& $this->groupStack[$level - 1];
            $parent['subgroups'] ??= [];
            $parent['subgroups'][] = &$groupNode;
        }

        $this->groupStack[$level] =& $groupNode;
    }

    private function renderDetail($record): void
    {
        $formattedRow = [];

        if ($this->report && $this->report->hasConfiguredColumns()) {
            foreach ($this->report->getColumnOrder() as $field) {
                $raw    = $record[$field] ?? null;
                $format = $this->report->getColumnFormat($field);

                $formattedRow[$field] = $this->formatValue($raw, $format);
            }
        } else {
            // Fallback: raw record
            $formattedRow = $record;
        }

        // === CRITICAL FIX: Handle both grouped and ungrouped cases ===

        if (!empty($this->groupStack)) {
            // We are inside one or more groups → add to the deepest (current) group
            $currentGroup =& $this->groupStack[count($this->groupStack) - 1];
            $currentGroup['rows'][] = $formattedRow;
        } else {
            // No groups at all → treat top-level 'groups' as flat list of rows
            $this->result['groups'][] = $formattedRow;
        }
    }

    private function renderGroupFooter(int $level, array $context): void
    {
        // Aggregates already added in groupHeader via context.
        // Here we just clean up the stack.
        unset($this->groupStack[$level]);
    }

    private function renderSummary(array $context): void
    {
        $this->result['summary'] = [
            'recordCount' => $context['recordCount'] ?? 0,
            'aggregates'  => [],
        ];

        foreach ($context as $key => $value) {
            if ($key !== 'recordCount') {
                $this->result['summary']['aggregates'][$key] = $value;
            }
        }
    }
}