<?php

declare(strict_types=1);

namespace foreup\Reporting\Fill;

use foreup\Reporting\Expression\AggregateFunction;
use foreup\Reporting\Instance\BandInstance;
use foreup\Reporting\Instance\Content\TextContent;
use foreup\Reporting\Instance\ElementInstance;
use foreup\Reporting\Instance\Grouping;
use foreup\Reporting\Instance\ReportInstance;
use foreup\Reporting\Interfaces\ReportFillerInterface;
use foreup\Reporting\Registry\DataSourceRegistry;
use foreup\Reporting\Registry\FormatterRegistry;
use foreup\Reporting\Template\BandTemplate;
use foreup\Reporting\Template\ElementTemplate;
use foreup\Reporting\Template\ReportTemplate;

class DefinitionFiller implements ReportFillerInterface
{
    private ReportTemplate $template;
    private DataSourceRegistry $registry;
    private FormatterRegistry $formatters;
    /** @var array<string, callable[]> keyed by band definition id */
    private array $bandCallbacks = [];
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $rowCache = [];

    public function __construct(
        ReportTemplate $template,
        DataSourceRegistry $registry,
        FormatterRegistry $formatters
    ) {
        $this->template   = $template;
        $this->registry   = $registry;
        $this->formatters = $formatters;
    }

    /**
     * Register a callback for a band. The callback fires after the band is built,
     * before it is added to the report. Return null to suppress the band, or return
     * a (modified) BandInstance to use instead.
     *
     * Callbacks chain: each receives the output of the previous. If any returns null
     * the band is suppressed and remaining callbacks are skipped.
     *
     * @param callable(BandInstance, BandContext): ?BandInstance $callback
     */
    public function onBand(string $bandId, callable $callback): self
    {
        $this->bandCallbacks[$bandId][] = $callback;
        return $this;
    }

    public function fill(array $params): ReportInstance
    {
        $this->validateParams($params);
        $this->rowCache = [];
        $bands = $this->buildBands($params);
        return new ReportInstance($this->template->getReportDefinitionId(), $bands);
    }

    // ── Band building ─────────────────────────────────────────────────────────

    /** @return BandInstance[] */
    private function buildBands(array $params): array
    {
        $result        = [];
        $bandDefs      = $this->template->getBands();
        $count         = count($bandDefs);
        $defaultSource = $this->template->getDataSource();
        $i             = 0;

        while ($i < $count) {
            $bandDef    = $bandDefs[$i];
            $sourceName = $bandDef->getDataSource() ?? $defaultSource;
            $rows       = $this->getRowsFor($sourceName, $params);

            if ($bandDef->getType() === 'group-header') {
                $groupSlice = [];
                $j = $i;
                while ($j < $count) {
                    $groupSlice[] = $bandDefs[$j];
                    if ($bandDefs[$j]->getType() === 'group-footer') {
                        break;
                    }
                    $j++;
                }
                $i = $j + 1;

                $groups = Grouping::byField($rows, $bandDef->getGroupBy() ?? '');
                foreach ($groups as $groupValue => $groupRows) {
                    foreach ($groupSlice as $gDef) {
                        if ($gDef->getType() === 'group-header') {
                            $band = $this->applyCallbacks(
                                $gDef->getId(),
                                $this->staticBand($gDef, $params, (string) $groupValue),
                                new BandContext([], (string) $groupValue, $groupRows, $params)
                            );
                        } elseif ($gDef->getType() === 'detail') {
                            foreach ($groupRows as $rowIdx => $row) {
                                $band = $this->applyCallbacks(
                                    $gDef->getId(),
                                    $this->rowBand($gDef, $row, "{$groupValue}_{$rowIdx}", $params),
                                    new BandContext($row, (string) $groupValue, [], $params)
                                );
                                if ($band !== null) {
                                    $result[] = $band;
                                }
                            }
                            continue;
                        } elseif ($gDef->getType() === 'group-footer') {
                            $band = $this->applyCallbacks(
                                $gDef->getId(),
                                $this->aggregateBand($gDef, $groupRows, $params, (string) $groupValue),
                                new BandContext([], (string) $groupValue, $groupRows, $params)
                            );
                        } else {
                            $band = null;
                        }
                        if ($band !== null) {
                            $result[] = $band;
                        }
                    }
                }
                continue;
            }

            if ($bandDef->getType() === 'detail') {
                foreach ($rows as $rowIdx => $row) {
                    $band = $this->applyCallbacks(
                        $bandDef->getId(),
                        $this->rowBand($bandDef, $row, (string) $rowIdx, $params),
                        new BandContext($row, null, [], $params)
                    );
                    if ($band !== null) {
                        $result[] = $band;
                    }
                }
                $i++;
                continue;
            }

            if ($bandDef->getType() === 'summary') {
                $band = $this->applyCallbacks(
                    $bandDef->getId(),
                    $this->aggregateBand($bandDef, $rows, $params, null),
                    new BandContext([], null, $rows, $params)
                );
                if ($band !== null) {
                    $result[] = $band;
                }
                $i++;
                continue;
            }

            // Static: title, col-header, or any custom band type
            $band = $this->applyCallbacks(
                $bandDef->getId(),
                $this->staticBand($bandDef, $params, null),
                new BandContext([], null, [], $params)
            );
            if ($band !== null) {
                $result[] = $band;
            }
            $i++;
        }

        return $result;
    }

    private function applyCallbacks(string $bandDefId, BandInstance $band, BandContext $ctx): ?BandInstance
    {
        foreach ($this->bandCallbacks[$bandDefId] ?? [] as $callback) {
            $band = $callback($band, $ctx);
            if ($band === null) {
                return null;
            }
        }
        return $band;
    }

    /** @return array<int, array<string, mixed>> */
    private function getRowsFor(string $sourceName, array $params): array
    {
        if (!isset($this->rowCache[$sourceName])) {
            $this->rowCache[$sourceName] = $this->registry->get($sourceName)->fetchRows($params);
        }
        return $this->rowCache[$sourceName];
    }

    private function staticBand(BandTemplate $def, array $params, ?string $groupValue): BandInstance
    {
        $bandId   = 'band_' . $def->getId() . ($groupValue !== null ? '_' . $this->safeId($groupValue) : '');
        $elements = [];
        foreach ($def->getElements() as $elDef) {
            $elements[] = $this->resolveElement($elDef, [], [], $groupValue, $params, $bandId);
        }
        return new BandInstance($bandId, $def->getType(), $elements, null, $def->getRowSpacing());
    }

    private function rowBand(BandTemplate $def, array $row, string $rowKey, array $params): BandInstance
    {
        $bandId   = 'band_' . $def->getId() . '_' . $this->safeId($rowKey);
        $elements = [];
        foreach ($def->getElements() as $elDef) {
            $elements[] = $this->resolveElement($elDef, $row, [], null, $params, $bandId);
        }
        return new BandInstance($bandId, $def->getType(), $elements, null, $def->getRowSpacing());
    }

    private function aggregateBand(BandTemplate $def, array $rows, array $params, ?string $groupValue): BandInstance
    {
        $bandId   = 'band_' . $def->getId() . ($groupValue !== null ? '_' . $this->safeId($groupValue) : '');
        $elements = [];
        foreach ($def->getElements() as $elDef) {
            $elements[] = $this->resolveElement($elDef, [], $rows, $groupValue, $params, $bandId);
        }
        return new BandInstance($bandId, $def->getType(), $elements, null, $def->getRowSpacing());
    }

    // ── Element resolution ────────────────────────────────────────────────────

    private function resolveElement(
        ElementTemplate $elDef,
        array $row,
        array $aggregateRows,
        ?string $groupValue,
        array $params,
        string $bandId
    ): ElementInstance {
        $content = $elDef->getContent();

        switch ($content->getType()) {
            case 'text':
                $value = $this->interpolate($content->getValue() ?? '', $params);
                break;
            case 'field':
                $raw   = $row[$content->getField() ?? ''] ?? '';
                $value = $this->applyFormat($raw, $content->getFormat());
                break;
            case 'aggregate':
                $raw   = $this->computeAggregate($aggregateRows, $content->getField() ?? '', $content->getFn() ?? 'sum');
                $value = $this->applyFormat($raw, $content->getFormat());
                break;
            case 'group_value':
                $value = $groupValue ?? '';
                break;
            default:
                $value = '';
        }

        return new ElementInstance(
            $bandId . '_' . $elDef->getId(),
            $elDef->getX(),
            0.0,
            $elDef->getWidth(),
            $elDef->getHeight(),
            new TextContent($value),
            $elDef->getAlign()
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @param mixed $value */
    private function applyFormat($value, ?string $format): string
    {
        if ($format === null) {
            return (string) $value;
        }
        return ($this->formatters->get($format))($value);
    }

    private function computeAggregate(array $rows, string $field, string $fn): float
    {
        return AggregateFunction::apply($fn, $rows, $field);
    }

    private function interpolate(string $template, array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs['{' . $key . '}'] = (string) $value;
        }
        return strtr($template, $pairs);
    }

    private function safeId(string $value): string
    {
        return preg_replace('/[^a-z0-9_]/', '_', strtolower($value));
    }

    private function validateParams(array $params): void
    {
        foreach ($this->template->getParams() as $param) {
            if (!$param->isRequired()) {
                continue;
            }
            $value = $params[$param->getName()] ?? null;
            if ($value === null || $value === '') {
                throw new \InvalidArgumentException("Missing required param: '{$param->getName()}'");
            }
        }
    }
}
