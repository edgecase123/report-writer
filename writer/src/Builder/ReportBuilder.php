<?php

declare(strict_types=1);

namespace ReportWriter\Builder;

use ReportWriter\Expression\EvalContext;
use ReportWriter\Expression\StaticExpression;
use ReportWriter\Instance\BandInstance;
use ReportWriter\Instance\Content\TextContent;
use ReportWriter\Instance\ElementInstance;
use ReportWriter\Instance\Grouping;
use ReportWriter\Instance\ReportInstance;

class ReportBuilder
{
    private string $reportId;
    private ?string $titleText    = null;
    private float $titleHeight    = 30.0;
    private float $headerHeight   = 15.0;
    private float $groupHdrHeight = 15.0;
    private float $rowHeight      = 12.0;
    private float $groupFtrHeight = 15.0;
    private float $summaryHeight  = 15.0;

    /** @var Column[] */
    private array $columns = [];

    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    /** @var string[] */
    private array $groupKeys = [];

    private function __construct(string $reportId)
    {
        $this->reportId = $reportId;
    }

    public static function create(string $reportId): self
    {
        return new self($reportId);
    }

    public function title(string $text, float $height = 30.0): self
    {
        $clone              = clone $this;
        $clone->titleText   = $text;
        $clone->titleHeight = $height;
        return $clone;
    }

    /** @param Column[] $columns */
    public function columns(array $columns): self
    {
        $clone          = clone $this;
        $clone->columns = $columns;
        return $clone;
    }

    /** @param array<int, array<string, mixed>> $rows */
    public function rows(array $rows): self
    {
        $clone       = clone $this;
        $clone->rows = $rows;
        return $clone;
    }

    public function groupBy(string ...$keys): self
    {
        $clone            = clone $this;
        $clone->groupKeys = $keys;
        return $clone;
    }

    public function rowHeight(float $h): self
    {
        $clone            = clone $this;
        $clone->rowHeight = $h;
        return $clone;
    }

    public function build(): ReportInstance
    {
        $bands = [];

        if ($this->titleText !== null) {
            $totalWidth = $this->totalWidth();
            $bands[] = new BandInstance('band_title', 'title', [
                new ElementInstance('title', 0.0, 0.0, $totalWidth, $this->titleHeight, new TextContent($this->titleText)),
            ]);
        }

        $bands[] = new BandInstance('band_col_hdr', 'col-header', $this->headerElements());

        if (!empty($this->groupKeys)) {
            $result = $this->buildGroupBands($this->rows, $this->groupKeys, 'g');
            foreach ($result['bands'] as $b) {
                $bands[] = $b;
            }
            $grandRows = $result['rows'];
        } else {
            $grandRows = [];
            foreach ($this->rows as $rowIdx => $row) {
                $bands[]     = new BandInstance("band_detail_r{$rowIdx}", 'detail', $this->detailElements("r{$rowIdx}", $row));
                $grandRows[] = $row;
            }
        }

        $bands[] = new BandInstance(
            'band_summary', 'summary',
            $this->summaryElements($grandRows)
        );

        return new ReportInstance($this->reportId, $bands);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function totalWidth(): float
    {
        $max = 0.0;
        foreach ($this->columns as $col) {
            $edge = $col->getX() + $col->getWidth();
            if ($edge > $max) {
                $max = $edge;
            }
        }
        return $max;
    }

    /** @return ElementInstance[] */
    private function headerElements(): array
    {
        $elements = [];
        foreach ($this->columns as $col) {
            [$elX, $elW] = $this->applyMargin($col);
            $elements[] = new ElementInstance(
                'ch_' . $col->getId(), $elX, 0.0, $elW, $this->headerHeight,
                new TextContent((new StaticExpression($col->getHeader()))->evaluate(new EvalContext())),
                $col->getTextAlign()
            );
        }
        return $elements;
    }

    /** @return ElementInstance[] */
    private function detailElements(string $prefix, array $row): array
    {
        $ctx      = new EvalContext($row, [], []);
        $elements = [];
        foreach ($this->columns as $col) {
            [$elX, $elW] = $this->applyMargin($col);
            $elements[] = new ElementInstance(
                "{$prefix}_{$col->getId()}", $elX, 0.0, $elW, $this->rowHeight,
                new TextContent($col->getDetailExpr()->evaluate($ctx)),
                $col->getTextAlign()
            );
        }
        return $elements;
    }

    /**
     * @param  array<int, array<string, mixed>> $rows
     * @return ElementInstance[]
     */
    private function footerElements(string $prefix, array $rows, float $height): array
    {
        $ctx      = new EvalContext([], $rows, []);
        $elements = [];
        foreach ($this->columns as $col) {
            [$elX, $elW] = $this->applyMargin($col);
            $expr        = $col->getFooterExpr();
            $text        = $expr !== null ? $expr->evaluate($ctx) : '';
            $elements[]  = new ElementInstance(
                "{$prefix}_{$col->getId()}", $elX, 0.0, $elW, $height,
                new TextContent($text),
                $col->getTextAlign()
            );
        }
        return $elements;
    }

    /**
     * @param  array<int, array<string, mixed>> $rows
     * @return ElementInstance[]
     */
    private function summaryElements(array $rows): array
    {
        $ctx      = new EvalContext([], $rows, []);
        $elements = [];
        foreach ($this->columns as $col) {
            [$elX, $elW] = $this->applyMargin($col);
            $expr        = $col->getSummaryExpr();
            $text        = $expr !== null ? $expr->evaluate($ctx) : '';
            $elements[]  = new ElementInstance(
                "summary_{$col->getId()}", $elX, 0.0, $elW, $this->summaryHeight,
                new TextContent($text),
                $col->getTextAlign()
            );
        }
        return $elements;
    }

    /** @return array{0: float, 1: float} [x, width] after margin applied */
    private function applyMargin(Column $col): array
    {
        return [
            $col->getX()     + $col->getMarginLeft(),
            $col->getWidth() - $col->getMarginLeft() - $col->getMarginRight(),
        ];
    }

    /**
     * @param  string[] $keys
     * @return array{bands: BandInstance[], rows: array<int, array<string, mixed>>}
     */
    private function buildGroupBands(array $rows, array $keys, string $prefix): array
    {
        $bands      = [];
        $allRows    = [];
        $key        = $keys[0];
        $remaining  = array_slice($keys, 1);
        $isLeaf     = empty($remaining);
        $totalWidth = $this->totalWidth();
        $groupIdx   = 0;

        foreach (Grouping::byField($rows, $key) as $groupValue => $groupRows) {
            $safeId  = $prefix . $groupIdx++;

            $bands[] = new BandInstance("band_grp_hdr_{$safeId}", 'group-header', [
                new ElementInstance(
                    "grp_hdr_{$safeId}", 0.0, 0.0, $totalWidth, $this->groupHdrHeight,
                    new TextContent((string) $groupValue)
                ),
            ]);

            if ($isLeaf) {
                foreach ($groupRows as $rowIdx => $row) {
                    $bands[]   = new BandInstance("band_detail_{$safeId}_r{$rowIdx}", 'detail', $this->detailElements("{$safeId}_r{$rowIdx}", $row));
                    $allRows[] = $row;
                }
                $footerRows = $groupRows;
            } else {
                $result     = $this->buildGroupBands($groupRows, $remaining, $safeId . '_');
                foreach ($result['bands'] as $b) {
                    $bands[] = $b;
                }
                foreach ($result['rows'] as $r) {
                    $allRows[] = $r;
                }
                $footerRows = $result['rows'];
            }

            $bands[] = new BandInstance(
                "band_grp_ftr_{$safeId}", 'group-footer',
                $this->footerElements("grp_ftr_{$safeId}", $footerRows, $this->groupFtrHeight)
            );
        }

        return ['bands' => $bands, 'rows' => $allRows];
    }

}
