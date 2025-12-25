<?php

namespace ReportWriter\Report;

use Iterator;
use ReportWriter\Report\Band\BandInterface;
use ReportWriter\Report\Builder\GroupBuilder;
use ReportWriter\Report\Data\DataProviderInterface;

/**
 * Abstract Report class. Defines common helper methods and state.
 */
abstract class AbstractReport implements ReportInterface
{
    protected DataProviderInterface $dataProvider;

    /** @var BandInterface[] */
    protected array $bands = [];

    /** @var GroupBuilder[] */
    protected array $groupBuilders = [];

    /** @var array $groupStack */
    private array $groupStack = [];

    /** @var array $groupStates */
    private array $groupStates = [];

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

        $previousGroupKeys = [];  // Start empty — we will treat empty as "no previous"

        while ($current !== null) {
            $currentGroupKeys = $this->computeGroupKeys($current);
            $changedLevels = $this->detectGroupChanges($previousGroupKeys, $currentGroupKeys);

            // 1. Close breaking groups FIRST (so they include the current record in aggregates)
            if (!empty($previousGroupKeys)) {
                foreach (array_reverse($changedLevels) as $level => $oldKey) {
                    $fullKey = implode('|', array_slice($previousGroupKeys, 0, $level + 1));
                    $context = $this->buildGroupContext($fullKey);
                    $output .= $this->renderBand('groupFooter', $level, $context);
                    unset($this->groupStates[$fullKey]);
                }
            }

            // 2. Open new/changed groups
            foreach ($changedLevels as $level => $newKey) {
                $fullKey = implode('|', array_slice($currentGroupKeys, 0, $level + 1));
                $this->initializeGroupState($level, $fullKey);

                // First record of this group is the current one
                $this->groupStates[$fullKey]['firstRecord'] = $current;

                $context = $this->buildGroupContext($fullKey);
                $output .= $this->renderBand('groupHeader', $level, $context);
            }

            // 3. Update group stack to current
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
        $output .= $this->renderBand('summary');
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

        // <<< TEMPORARY DEBUG >>>
        error_log("buildGroupContext($fullKey) - aggregates count: " . count($state['aggregates'] ?? []));
        error_log("buildGroupContext($fullKey) - aggregate keys: " . implode(', ', array_keys($state['aggregates'] ?? [])));

        $context = [
            'firstRecord' => $state['firstRecord'] ?? null,
            'lastRecord'  => $state['lastRecord'] ?? null,
            'recordCount' => count($state['records'] ?? []),
        ];

        foreach ($state['aggregates'] as $name => $agg) {
            error_log("Adding aggregate '$name' with value " . $agg->getValue());
            $context[$name] = $agg->getValue();
        }

        foreach ($state['calculations'] as $calc) {
            $context[$calc['as']] = ($calc['callback'])($state + $context);
        }

        return $context;
    }

    protected function renderBand(string $type, ?int $level = null, $context = null): string
    {
        $name = $level !== null ? $type . '_' . $level : $type;
        return "<!-- $name band rendered with context: " . json_encode($context) . " -->\n";
    }
}
