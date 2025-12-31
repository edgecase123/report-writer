<?php

declare(strict_types=1);

namespace ReportWriter\Report\Renderer;

use ReportWriter\Report\AbstractReport;
use DateTimeImmutable;

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
                // Nothing needed for JSON
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
        ];

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
            'subgroups'  => [], // always initialize for consistency
        ];

        // Place the new group in the correct parent
        if ($level === 0) {
            // Top-level group
            $this->result['groups'][] =& $groupNode;
        } else {
            // Nested inside previous level
            $parent =& $this->groupStack[$level - 1];
            $parent['subgroups'][] =& $groupNode;
        }

        // Push onto stack so detail rows know where to go
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
            $formattedRow = $record;
        }

        // Ensure we always have a current group to attach rows to
        if (empty($this->groupStack)) {
            // No explicit grouping → create an implicit level-0 group on first detail
            $implicitGroup = [
                'level'      => 0,
                'value'      => null,          // no grouping value
                'aggregates' => [],
                'rows'       => [],
                'subgroups'  => [],
            ];

            $this->result['groups'][] =& $implicitGroup;
            $this->groupStack[0] =& $implicitGroup;
        }

        // Now add the row to the deepest (current) group
        $currentGroup =& $this->groupStack[count($this->groupStack) - 1];
        $currentGroup['rows'][] = $formattedRow;
    }

    private function renderGroupFooter(int $level, array $context): void
    {
        // Find the group node on the stack
        $groupNode =& $this->groupStack[$level];

        // Now populate aggregates with final values
        foreach ($context as $key => $value) {
            if (!in_array($key, ['groupValue', 'firstRecord', 'lastRecord', 'recordCount', 'rows'], true)) {
                $groupNode['aggregates'][$key] = $value;
            }
        }

        // Optionally add recordCount or other metadata
        $groupNode['recordCount'] = $context['recordCount'] ?? 0;

        // Aggregates were already added in the header via context
        // Just remove this level from the stack
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