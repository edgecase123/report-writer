<?php

declare(strict_types=1);

namespace ReportWriter\Builder;

use ReportWriter\Expression\ContentExpression;
use ReportWriter\Expression\EvalContext;
use ReportWriter\Expression\StaticExpression;
use ReportWriter\Fill\BandContext;
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

    /** @var callable[] */
    private array $beforeBuildCallbacks = [];
    /** @var callable[] */
    private array $afterBuildCallbacks = [];
    /** @var array<string, callable[]> keyed by band-type ('title', 'col-header', 'detail', 'group-header', 'group-footer', 'summary') */
    private array $bandCallbacks = [];

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

    /**
     * Register a callback that fires before `build()` constructs bands, receiving
     * the rows array and returning a (possibly transformed) rows array.
     *
     * Immutable-fluent: returns a clone. Callers MUST reassign
     * (`$b = $b->beforeBuild(...)`); fire-and-forget silently drops the callback.
     *
     * Non-nullable return type: to no-op, return the input unchanged.
     *
     * SECURITY: code-only API. Never construct $callback from data (JSON/DB/HTTP)
     * via Closure::fromCallable() or dynamic dispatch — see security-scanner R5.
     *
     * @param callable(array): array $callback
     */
    public function beforeBuild(callable $callback): self
    {
        $clone = clone $this;
        $clone->beforeBuildCallbacks[] = $callback;
        return $clone;
    }

    /**
     * Register a callback that fires just before `build()` returns. Receives the
     * ReportInstance and returns a (possibly transformed) ReportInstance.
     *
     * Immutable-fluent: returns a clone. Callers MUST reassign.
     *
     * Non-nullable return type: to no-op, return the input unchanged.
     *
     * SECURITY: code-only API. Never construct $callback from data (JSON/DB/HTTP)
     * via Closure::fromCallable() or dynamic dispatch — see security-scanner R5.
     *
     * @param callable(ReportInstance): ReportInstance $callback
     */
    public function afterBuild(callable $callback): self
    {
        $clone = clone $this;
        $clone->afterBuildCallbacks[] = $callback;
        return $clone;
    }

    /**
     * Register a callback for a band-type. The callback fires after each band of
     * the given type is built, before it is added to the report. Return null to
     * suppress the band, or return a (modified) BandInstance to use instead.
     *
     * The `$bandType` key matches the band-TYPE string ('title', 'col-header',
     * 'detail', 'group-header', 'group-footer', 'summary') — not a per-instance
     * ID. Register once against 'detail' to fire for every detail band.
     *
     * Callbacks chain: each receives the output of the previous. If any returns
     * null the band is suppressed and remaining callbacks are skipped.
     *
     * Immutable-fluent: returns a clone. Callers MUST reassign.
     *
     * SECURITY: code-only API. Never construct $callback from data (JSON/DB/HTTP)
     * via Closure::fromCallable() or dynamic dispatch — see security-scanner R5.
     *
     * @param callable(BandInstance, BandContext): ?BandInstance $callback
     */
    public function onBand(string $bandType, callable $callback): self
    {
        $clone = clone $this;
        $clone->bandCallbacks[$bandType][] = $callback;
        return $clone;
    }

    public function build(): ReportInstance
    {
        $rows = array_reduce(
            $this->beforeBuildCallbacks,
            fn ($acc, $cb) => $cb($acc),
            $this->rows
        );

        $bands = [];

        if ($this->titleText !== null) {
            $titleBand  = new BandInstance('band_title', 'title', [
                new ElementInstance('title', 0.0, 0.0, 0.0, $this->titleHeight, new TextContent($this->titleText)),
            ]);
            $titleBand = $this->applyBandCallbacks('title', $titleBand, new BandContext([], null, [], []));
            if ($titleBand !== null) {
                $bands[] = $titleBand;
            }
        }

        $colHdrBand = new BandInstance('band_col_hdr', 'col-header', $this->headerElements());
        $colHdrBand = $this->applyBandCallbacks('col-header', $colHdrBand, new BandContext([], null, [], []));
        if ($colHdrBand !== null) {
            $bands[] = $colHdrBand;
        }

        if (!empty($this->groupKeys)) {
            $result = $this->buildGroupBands($rows, $this->groupKeys, 'g');
            foreach ($result['bands'] as $b) {
                $bands[] = $b;
            }
            $grandRows = $result['rows'];
        } else {
            $grandRows = [];
            foreach ($rows as $rowIdx => $row) {
                $detailBand = new BandInstance("band_detail_r{$rowIdx}", 'detail', $this->detailElements("r{$rowIdx}", $row));
                $detailBand = $this->applyBandCallbacks('detail', $detailBand, new BandContext($row, null, [], []));
                if ($detailBand !== null) {
                    $bands[] = $detailBand;
                }
                $grandRows[] = $row;
            }
        }

        $summaryBand = new BandInstance(
            'band_summary', 'summary',
            $this->summaryElements($grandRows)
        );
        $summaryBand = $this->applyBandCallbacks('summary', $summaryBand, new BandContext([], null, $grandRows, []));
        if ($summaryBand !== null) {
            $bands[] = $summaryBand;
        }

        $instance = new ReportInstance($this->reportId, $bands);

        return array_reduce(
            $this->afterBuildCallbacks,
            fn ($acc, $cb) => $cb($acc),
            $instance
        );
    }

    private function applyBandCallbacks(string $bandType, BandInstance $band, BandContext $ctx): ?BandInstance
    {
        foreach ($this->bandCallbacks[$bandType] ?? [] as $callback) {
            $band = $callback($band, $ctx);
            if ($band === null) {
                return null;
            }
        }
        return $band;
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
        return $this->buildRowElements(
            'ch',
            new EvalContext(),
            fn (Column $col) => new StaticExpression($col->getHeader()),
            $this->headerHeight
        );
    }

    /** @return ElementInstance[] */
    private function detailElements(string $prefix, array $row): array
    {
        return $this->buildRowElements(
            $prefix,
            new EvalContext($row, [], []),
            fn (Column $col) => $col->getDetailExpr(),
            $this->rowHeight
        );
    }

    /**
     * @param  array<int, array<string, mixed>> $rows
     * @return ElementInstance[]
     */
    private function footerElements(string $prefix, array $rows, float $height): array
    {
        return $this->buildRowElements(
            $prefix,
            new EvalContext([], $rows, []),
            fn (Column $col) => $col->getFooterExpr(),
            $height
        );
    }

    /**
     * @param  array<int, array<string, mixed>> $rows
     * @return ElementInstance[]
     */
    private function summaryElements(array $rows): array
    {
        return $this->buildRowElements(
            'summary',
            new EvalContext([], $rows, []),
            fn (Column $col) => $col->getSummaryExpr(),
            $this->summaryHeight
        );
    }

    /**
     * Builds one ElementInstance per column, using $exprFor to select which
     * expression to evaluate per column. Extracted from the 4 near-identical
     * *Elements() loops (see Ticket 003).
     *
     * @param callable(Column): ?ContentExpression $exprFor
     * @return ElementInstance[]
     */
    private function buildRowElements(
        string $idPrefix,
        EvalContext $ctx,
        callable $exprFor,
        float $height
    ): array {
        $elements = [];
        foreach ($this->columns as $col) {
            [$elX, $elW] = $this->applyMargin($col);
            $expr        = $exprFor($col);
            $text        = $expr !== null ? $expr->evaluate($ctx) : '';
            $elements[]  = new ElementInstance(
                "{$idPrefix}_{$col->getId()}", $elX, 0.0, $elW, $height,
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

            $groupHdrBand = new BandInstance("band_grp_hdr_{$safeId}", 'group-header', [
                new ElementInstance(
                    "grp_hdr_{$safeId}", 0.0, 0.0, $totalWidth, $this->groupHdrHeight,
                    new TextContent((string) $groupValue)
                ),
            ]);
            $groupHdrBand = $this->applyBandCallbacks(
                'group-header',
                $groupHdrBand,
                new BandContext([], (string) $groupValue, $groupRows, [])
            );
            if ($groupHdrBand !== null) {
                $bands[] = $groupHdrBand;
            }

            if ($isLeaf) {
                foreach ($groupRows as $rowIdx => $row) {
                    $detailBand = new BandInstance("band_detail_{$safeId}_r{$rowIdx}", 'detail', $this->detailElements("{$safeId}_r{$rowIdx}", $row));
                    $detailBand = $this->applyBandCallbacks('detail', $detailBand, new BandContext($row, null, [], []));
                    if ($detailBand !== null) {
                        $bands[] = $detailBand;
                    }
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

            $groupFtrBand = new BandInstance(
                "band_grp_ftr_{$safeId}", 'group-footer',
                $this->footerElements("grp_ftr_{$safeId}", $footerRows, $this->groupFtrHeight)
            );
            $groupFtrBand = $this->applyBandCallbacks(
                'group-footer',
                $groupFtrBand,
                new BandContext([], (string) $groupValue, $footerRows, [])
            );
            if ($groupFtrBand !== null) {
                $bands[] = $groupFtrBand;
            }
        }

        return ['bands' => $bands, 'rows' => $allRows];
    }

}
