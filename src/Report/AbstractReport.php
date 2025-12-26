<?php

namespace ReportWriter\Report;

use ReportWriter\Report\Band\BandInterface;
use ReportWriter\Report\Builder\GroupBuilder;
use ReportWriter\Report\Data\DataProviderInterface;
use ReportWriter\Report\Renderer\RendererInterface;

/**
 * Abstract Report class. Defines common helper methods and state.
 */
abstract class AbstractReport implements ReportInterface
{
    protected ?RendererInterface $renderer = null;

    protected DataProviderInterface $dataProvider;

    /** @var BandInterface[] */
    protected array $bands = [];

    /** @var GroupBuilder[] */
    protected array $groupBuilders = [];

    /** @var array $groupStack */
    private array $groupStack = [];

    /** @var array $groupStates */
    private array $groupStates = [];

    /** @var Aggregate[] */
    private array $reportAggregates = [];

    /** @var array<string, string>  field => label */
    private array $columns = [];

    /** @var string[] Ordered list of fields to display */
    private array $columnOrder = [];

    public function setRenderer(RendererInterface $renderer): self
    {
        $this->renderer = $renderer;

        return $this;
    }

    public function getRenderer(): ?RendererInterface
    {
        return $this->renderer;
    }

    public function setDataProvider(DataProviderInterface $dataProvider): self
    {
        $this->dataProvider = $dataProvider;
        return $this;
    }

    public function setGroups(array $groupBuilders): self
    {
        $this->groupBuilders = $groupBuilders;

        return $this;
    }

    public function addBand(BandInterface $band): self
    {
        $this->bands[] = $band;

        return $this;
    }

    /**
     * Basic implementation used as default.
     * @return string
     */
    public function render(): string
    {
        $output = '';

        $iterator = $this->dataProvider->getRecords();
        $current = $iterator->valid() ? $iterator->current() : null;

        if ($iterator->valid()) {
            $iterator->next();
        }

        // Render report header
        $output .= $this->renderBand('reportHeader');

        $previousGroupKeys = [];  // Empty indicates no previous group key

        while ($current !== null) {
            $currentGroupKeys = $this->computeGroupKeys($current);

            // Detect if there's a group break and at which level (0 = outermost)
            $breakLevel = $this->findGroupBreakLevel($previousGroupKeys, $currentGroupKeys);

            if ($breakLevel !== null && !empty($previousGroupKeys)) {
                // Only close footers if we have previous groups
                $numLevels = count($this->groupBuilders);
                for ($level = $numLevels - 1; $level >= $breakLevel; $level--) {
                    $fullKey = implode('|', array_slice($previousGroupKeys, 0, $level + 1));
                    $context = $this->buildGroupContext($fullKey);
                    $output .= $this->renderBand('groupFooter', $level, $context);
                    unset($this->groupStates[$fullKey]);
                }
            }

            // Always open new headers if there was a break (even on the first record)
            if ($breakLevel !== null) {
                $numLevels = count($this->groupBuilders);
                for ($level = $breakLevel; $level < $numLevels; $level++) {
                    $fullKey = implode('|', array_slice($currentGroupKeys, 0, $level + 1));
                    $this->initializeGroupState($level, $fullKey);
                    $this->groupStates[$fullKey]['firstRecord'] = $current;

                    $context = $this->buildGroupContext($fullKey);
                    $output .= $this->renderBand('groupHeader', $level, $context);
                }
            }

            // Update group stack to current
            $this->groupStack = $currentGroupKeys;

            // Accumulate the current record into ALL active groups (old ones already
            // closed, new ones just opened)
            foreach ($this->groupStates as $fullKey => &$state) {
                foreach ($state['aggregates'] as $agg) {
                    $agg->accumulate($current);
                }

                $state['records'][] = $current;
                $state['lastRecord'] = $current;
                // Note that firstRecord already set above for new groups;
                // for ongoing groups it was set earlier
            }

            // Accumulate into report-level aggregates
            foreach ($this->reportAggregates as $agg) {
                $agg->accumulate($current);
            }

            // Render detail band
            $output .= $this->renderBand('detail', null, $current);

            // Prepare for the next iteration
            $previousGroupKeys = $currentGroupKeys;

            $current = $iterator->valid() ? $iterator->current() : null;
            if ($iterator->valid()) {
                $iterator->next();
            }
        }

        // Final group footers
        for ($level = count($this->groupStack) - 1; $level >= 0; $level--) {
            $fullKey = implode('|', array_slice($this->groupStack, 0, $level + 1));
            $context = $this->buildGroupContext($fullKey);
            $output .= $this->renderBand('groupFooter', $level, $context);
        }

        // Summary and footer
        // Build report-level context

        foreach ($this->reportAggregates as $name => $agg) {
            $reportContext[$name] = $agg->getValue();
        }

        // Count total records
        $totalRecords = 0;

        foreach ($this->groupStates as $state) {
            $totalRecords += count($state['records']);
        }

        $reportContext['recordCount'] = $totalRecords ?? 0;

        $output .= $this->renderBand('summary', null, $reportContext);
        $output .= $this->renderBand('reportFooter');

        return $output;
    }

    private function computeGroupKeys($record): array
    {
        $keys = [];
        foreach ($this->groupBuilders as $builder) {
            $expr = $builder->getExpression();
            $value = is_callable($expr) ? $expr($record) : ($record->{$expr} ?? $record[$expr] ?? null);
            $keys[] = is_object($value) ? spl_object_hash($value) : (string)$value;
        }
        return $keys;
    }

    /**
     * Returns the lowest level (most outer) where a group break occurred.
     * Returns null if no break.
     */
    private function findGroupBreakLevel(array $previous, array $current): ?int
    {
        $maxLevel = max(count($previous), count($current)) - 1;

        for ($level = 0; $level <= $maxLevel; $level++) {
            $prev = $previous[$level] ?? null;
            $curr = $current[$level] ?? null;

            if ($prev !== $curr) {
                return $level; // First difference = the break level
            }
        }

        return null;
    }

    private function detectGroupChanges(array $previous, array $current): array
    {
        $changes = [];

        // Get the least number of keys to process
        $min = min(count($previous), count($current));

        for ($i = 0; $i < $min; $i++) {
            if ($previous[$i] !== $current[$i]) {
                $changes[$i] = $current[$i];
            }
        }

        for ($i = $min; $i < count($current); $i++) {
            $changes[$i] = $current[$i];
        }

        return $changes;
    }

    private function initializeGroupState(int $level, string $fullKey): void
    {
        $builder = $this->groupBuilders[$level];
        $aggregates = [];

        foreach ($builder->getAggregates() as $def) {
            $aggregates[$def['as']] = new Aggregate($def['type'], $def['field']);

            if (!isset($this->reportAggregates[$def['as']])) {
                $this->reportAggregates[$def['as']] = new Aggregate($def['type'], $def['field']);
            }
        }

        $this->groupStates[$fullKey] = [
            'aggregates' => $aggregates,
            'calculations' => $builder->getCalculations(),
            'records' => [],
            'firstRecord' => null,
            'lastRecord' => null,
        ];
    }

    private function buildGroupContext(string $fullKey): array
    {
        $state = $this->groupStates[$fullKey] ?? ['aggregates' => [], 'calculations' => [], 'records' => []];

        $context = [
            'firstRecord' => $state['firstRecord'] ?? null,
            'lastRecord'  => $state['lastRecord'] ?? null,
            'recordCount' => count($state['records'] ?? []),
        ];

        foreach ($state['aggregates'] as $name => $agg) {
            $context[$name] = $agg->getValue();
        }

        foreach ($state['calculations'] as $calc) {
            $context[$calc['as']] = ($calc['callback'])($state + $context);
        }

        return $context;
    }

    protected function renderBand(string $type, ?int $level = null, $context = null): string
    {
        if ($this->renderer !== null) {
            return $this->renderer->renderBand($type, $level, $context);
        }

        // If not Renderer used, then output debug info
        $name = $level !== null ? $type . '_' . $level : $type;
        return "<!-- $name band rendered with context: " . json_encode($context) . " -->\n";
    }

    public function setColumns(array $columns): self
    {
        // $columns can be ['field' => 'Label'] or simple ['field1', 'field2']
        $this->columns = [];
        $this->columnOrder = [];

        foreach ($columns as $field => $label) {
            if (is_int($field)) {
                // Numeric key → simple list of fields, use ucfirst as label
                $field = $label;
                $label = ucfirst($field);
            }
            $this->columns[$field] = $label;
            $this->columnOrder[] = $field;
        }

        return $this;
    }

    public function getColumnOrder(): array
    {
        return $this->columnOrder;
    }

    public function getColumnLabel(string $field): string
    {
        return $this->columns[$field] ?? ucfirst($field);
    }

    public function hasConfiguredColumns(): bool
    {
        return !empty($this->columnOrder);
    }
}
