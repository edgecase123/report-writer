<?php

declare(strict_types=1);

namespace Tests\Unit\Builder;

use ReportWriter\Builder\Column;
use ReportWriter\Builder\ReportBuilder;
use ReportWriter\Expression\StaticExpression;
use ReportWriter\Instance\BandInstance;
use PHPUnit\Framework\TestCase;

class ReportBuilderTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function itemCol(): Column
    {
        return Column::make('item', 'Item', 0.0, 200.0);
    }

    private function totalCol(): Column
    {
        return Column::make('total', 'Total', 200.0, 100.0)
            ->sum()
            ->format(fn($v) => '$' . number_format((float) $v, 2));
    }

    private function baseBuilder(): ReportBuilder
    {
        return ReportBuilder::create('test_report')
            ->columns([$this->itemCol(), $this->totalCol()]);
    }

    private function val(BandInstance $band, float $x): string
    {
        foreach ($band->getElements() as $el) {
            if ($el->getX() === $x) {
                return $el->getContent()->toArray()['value'];
            }
        }
        $this->fail(sprintf('No element at x=%.1f in band %s', $x, $band->getBandInstanceId()));
    }

    private function row(string $item, float $total, string $category = ''): array
    {
        return ['item' => $item, 'total' => $total, 'category' => $category];
    }

    // ── Band structure ────────────────────────────────────────────────────────

    public function testNoTitleProducesTwoBandsWithNoRows(): void
    {
        $bands = $this->baseBuilder()->build()->getBandInstances();

        $this->assertCount(2, $bands);
        $this->assertSame('band_col_hdr', $bands[0]->getBandInstanceId());
        $this->assertSame('band_summary', $bands[1]->getBandInstanceId());
    }

    public function testTitleProducesThreeBandsWithNoRows(): void
    {
        $bands = $this->baseBuilder()->title('My Report')->build()->getBandInstances();

        $this->assertCount(3, $bands);
        $this->assertSame('band_title',   $bands[0]->getBandInstanceId());
        $this->assertSame('band_col_hdr', $bands[1]->getBandInstanceId());
        $this->assertSame('band_summary', $bands[2]->getBandInstanceId());
    }

    public function testFlatRowsProduceDetailBands(): void
    {
        $bands = $this->baseBuilder()
            ->rows([$this->row('A', 10.0), $this->row('B', 20.0)])
            ->build()->getBandInstances();

        // col_hdr + detail + detail + summary
        $this->assertCount(4, $bands);
        $this->assertSame('col-header', $bands[0]->getBandType());
        $this->assertSame('detail',     $bands[1]->getBandType());
        $this->assertSame('detail',     $bands[2]->getBandType());
        $this->assertSame('summary',    $bands[3]->getBandType());
    }

    public function testGroupedRowsProduceGroupBands(): void
    {
        $bands = $this->baseBuilder()
            ->groupBy('category')
            ->rows([$this->row('Hat', 15.0, 'Apparel')])
            ->build()->getBandInstances();

        // col_hdr + grp_hdr + detail + grp_ftr + summary
        $this->assertCount(5, $bands);
        $this->assertSame('col-header',   $bands[0]->getBandType());
        $this->assertSame('group-header', $bands[1]->getBandType());
        $this->assertSame('detail',       $bands[2]->getBandType());
        $this->assertSame('group-footer', $bands[3]->getBandType());
        $this->assertSame('summary',      $bands[4]->getBandType());
    }

    public function testTwoGroupsProduceCorrectBandCount(): void
    {
        $bands = $this->baseBuilder()
            ->groupBy('category')
            ->rows([
                $this->row('Hat',      15.0, 'Apparel'),
                $this->row('Shirt',    25.0, 'Apparel'),
                $this->row('Tee Time', 45.0, 'Golf'),
            ])
            ->build()->getBandInstances();

        // col_hdr + (grp_hdr + detail + detail + grp_ftr) + (grp_hdr + detail + grp_ftr) + summary = 9
        $this->assertCount(9, $bands);
    }

    public function testTwoLevelGroupingProducesCorrectBandStructure(): void
    {
        $bands = $this->baseBuilder()
            ->groupBy('category', 'subcategory')
            ->rows([
                ['item' => 'Hat',    'total' => 15.0, 'category' => 'Apparel', 'subcategory' => 'Headwear'],
                ['item' => 'Shirt',  'total' => 25.0, 'category' => 'Apparel', 'subcategory' => 'Tops'],
                ['item' => 'Driver', 'total' => 99.0, 'category' => 'Golf',    'subcategory' => 'Clubs'],
            ])
            ->build()->getBandInstances();

        $types = array_map(fn ($b) => $b->getBandType(), $bands);

        $this->assertSame([
            'col-header',
            'group-header', 'group-header', 'detail', 'group-footer',  // Apparel > Headwear
                            'group-header', 'detail', 'group-footer',  // Apparel > Tops
            'group-footer',                                             // Apparel
            'group-header', 'group-header', 'detail', 'group-footer',  // Golf > Clubs
            'group-footer',                                             // Golf
            'summary',
        ], $types);
    }

    public function testTwoLevelInnerFooterSumsOnlyItsRows(): void
    {
        $bands = $this->baseBuilder()
            ->groupBy('category', 'subcategory')
            ->rows([
                ['item' => 'Hat',   'total' => 15.0, 'category' => 'Apparel', 'subcategory' => 'Headwear'],
                ['item' => 'Shirt', 'total' => 25.0, 'category' => 'Apparel', 'subcategory' => 'Tops'],
            ])
            ->build()->getBandInstances();

        // col_hdr(0), Apparel hdr(1), Headwear hdr(2), detail(3), Headwear ftr(4)
        $this->assertSame('$15.00', $this->val($bands[4], 200.0));
    }

    public function testTwoLevelOuterFooterSumsItsSubgroups(): void
    {
        $bands = $this->baseBuilder()
            ->groupBy('category', 'subcategory')
            ->rows([
                ['item' => 'Hat',   'total' => 15.0, 'category' => 'Apparel', 'subcategory' => 'Headwear'],
                ['item' => 'Shirt', 'total' => 25.0, 'category' => 'Apparel', 'subcategory' => 'Tops'],
            ])
            ->build()->getBandInstances();

        // col_hdr(0), Apparel hdr(1), Headwear hdr(2), detail(3), Headwear ftr(4),
        // Tops hdr(5), detail(6), Tops ftr(7), Apparel ftr(8), summary(9)
        $this->assertSame('$40.00', $this->val($bands[8], 200.0));
    }

    public function testTwoLevelGrandTotalSumsAllGroups(): void
    {
        $bands = $this->baseBuilder()
            ->groupBy('category', 'subcategory')
            ->rows([
                ['item' => 'Hat',    'total' => 15.0, 'category' => 'Apparel', 'subcategory' => 'Headwear'],
                ['item' => 'Driver', 'total' => 99.0, 'category' => 'Golf',    'subcategory' => 'Clubs'],
            ])
            ->build()->getBandInstances();

        $this->assertSame('$114.00', $this->val($bands[count($bands) - 1], 200.0));
    }

    // ── Column headers ────────────────────────────────────────────────────────

    public function testColumnHeadersReflectColumnDefinitions(): void
    {
        $colHdr = $this->baseBuilder()->build()->getBandInstances()[0];

        $this->assertSame('Item',  $this->val($colHdr, 0.0));
        $this->assertSame('Total', $this->val($colHdr, 200.0));
    }

    // ── Title ─────────────────────────────────────────────────────────────────

    public function testTitleTextAppearedInFirstBand(): void
    {
        $bands = $this->baseBuilder()->title('My Sales Report')->build()->getBandInstances();
        $text  = $bands[0]->getElements()[0]->getContent()->toArray()['value'];

        $this->assertSame('My Sales Report', $text);
    }

    public function testTitleSpansFullColumnWidth(): void
    {
        $bands = $this->baseBuilder()->title('T')->build()->getBandInstances();
        $el    = $bands[0]->getElements()[0];

        // width=0 is the sentinel meaning "use printable page width" (Ticket 017)
        $this->assertSame(0.0, $el->getWidth());
    }

    public function testTitleElementDeclaresNoWidth(): void
    {
        // Title element should carry the width=0 sentinel so LayoutService
        // substitutes printable page width. This guards against any future
        // re-introduction of cross-band coupling (e.g. reading from $this->columns).
        $bands  = $this->baseBuilder()->title('My Report')->build()->getBandInstances();
        $title  = $bands[0];
        $this->assertSame('band_title', $title->getBandInstanceId());

        $elements = $title->getElements();
        $this->assertCount(1, $elements);
        $this->assertSame(0.0, $elements[0]->getWidth(),
            'title element must declare width=0 (no coupling to columns extent)');
    }

    // ── Detail rows ───────────────────────────────────────────────────────────

    public function testDetailRowValuesMapToColumnIds(): void
    {
        $bands  = $this->baseBuilder()
            ->rows([$this->row('Hat', 15.0)])
            ->build()->getBandInstances();
        $detail = $bands[1];

        $this->assertSame('Hat',    $this->val($detail, 0.0));
        $this->assertSame('$15.00', $this->val($detail, 200.0));
    }

    public function testColumnWithoutFormatterCastsValueToString(): void
    {
        $bands  = $this->baseBuilder()
            ->rows([['item' => 42, 'total' => 0.0]])
            ->build()->getBandInstances();

        $this->assertSame('42', $this->val($bands[1], 0.0));
    }

    // ── Grouping ──────────────────────────────────────────────────────────────

    public function testGroupHeaderShowsGroupValue(): void
    {
        $bands = $this->baseBuilder()
            ->groupBy('category')
            ->rows([$this->row('Hat', 15.0, 'Apparel')])
            ->build()->getBandInstances();

        $groupHdrText = $bands[1]->getElements()[0]->getContent()->toArray()['value'];
        $this->assertSame('Apparel', $groupHdrText);
    }

    public function testEmptyGroupKeyFallsBackToUncategorized(): void
    {
        $bands = $this->baseBuilder()
            ->groupBy('category')
            ->rows([$this->row('Mystery', 5.0, '')])
            ->build()->getBandInstances();

        $groupHdrText = $bands[1]->getElements()[0]->getContent()->toArray()['value'];
        $this->assertSame('Uncategorized', $groupHdrText);
    }

    // ── Footer aggregate values ───────────────────────────────────────────────

    public function testGroupFooterShowsAggregateValue(): void
    {
        $bands = $this->baseBuilder()
            ->groupBy('category')
            ->rows([
                $this->row('Hat',   15.0, 'Apparel'),
                $this->row('Shirt', 25.0, 'Apparel'),
            ])
            ->build()->getBandInstances();

        // col_hdr + grp_hdr + detail + detail + grp_ftr + summary
        $this->assertSame('$40.00', $this->val($bands[4], 200.0));
    }

    public function testGroupFooterNonAggregateColumnIsBlankByDefault(): void
    {
        $bands = $this->baseBuilder()
            ->groupBy('category')
            ->rows([$this->row('Hat', 15.0, 'Apparel')])
            ->build()->getBandInstances();

        $this->assertSame('', $this->val($bands[3], 0.0));
    }

    public function testGroupFooterExplicitStaticLabel(): void
    {
        $itemWithLabel = Column::make('item', 'Item', 0.0, 200.0)
            ->footerContent(new StaticExpression('Category Total'));

        $bands = ReportBuilder::create('r')
            ->columns([$itemWithLabel, $this->totalCol()])
            ->groupBy('category')
            ->rows([$this->row('Hat', 15.0, 'Apparel')])
            ->build()->getBandInstances();

        $this->assertSame('Category Total', $this->val($bands[3], 0.0));
    }

    public function testSummaryNonAggregateColumnIsBlankByDefault(): void
    {
        $summary = $this->baseBuilder()->build()->getBandInstances()[1];
        $this->assertSame('', $this->val($summary, 0.0));
    }

    public function testSummaryExplicitStaticLabel(): void
    {
        $itemWithLabel = Column::make('item', 'Item', 0.0, 200.0)
            ->summaryContent(new StaticExpression('Grand Total'));

        $summary = ReportBuilder::create('r')
            ->columns([$itemWithLabel, $this->totalCol()])
            ->build()->getBandInstances()[1];

        $this->assertSame('Grand Total', $this->val($summary, 0.0));
    }

    // ── Grand total ───────────────────────────────────────────────────────────

    public function testGrandTotalSumsAcrossGroups(): void
    {
        $bands = $this->baseBuilder()
            ->groupBy('category')
            ->rows([
                $this->row('Hat',      15.0, 'Apparel'),
                $this->row('Tee Time', 45.0, 'Golf'),
            ])
            ->build()->getBandInstances();

        $summary = $bands[count($bands) - 1];
        $this->assertSame('$60.00', $this->val($summary, 200.0));
    }

    public function testGrandTotalSumsFlatRows(): void
    {
        $bands = $this->baseBuilder()
            ->rows([$this->row('A', 10.0), $this->row('B', 30.0)])
            ->build()->getBandInstances();

        $summary = $bands[count($bands) - 1];
        $this->assertSame('$40.00', $this->val($summary, 200.0));
    }

    public function testGrandTotalZeroWhenNoRows(): void
    {
        $summary = $this->baseBuilder()->build()->getBandInstances()[1];
        $this->assertSame('$0.00', $this->val($summary, 200.0));
    }

    // ── Alignment ─────────────────────────────────────────────────────────────

    public function testColumnAlignmentAppliedToDetailElement(): void
    {
        $bands  = ReportBuilder::create('r')
            ->columns([Column::make('v', 'V', 0.0, 100.0)->alignRight()])
            ->rows([['v' => 'x']])
            ->build()->getBandInstances();
        $detail = $bands[1];

        $this->assertSame('right', $detail->getElements()[0]->getTextAlign());
    }

    public function testColumnAlignmentAppliedToHeader(): void
    {
        $bands  = ReportBuilder::create('r')
            ->columns([Column::make('v', 'V', 0.0, 100.0)->alignCenter()])
            ->build()->getBandInstances();
        $colHdr = $bands[0];

        $this->assertSame('center', $colHdr->getElements()[0]->getTextAlign());
    }

    // ── Margin ────────────────────────────────────────────────────────────────

    public function testMarginShiftsElementXAndReducesWidth(): void
    {
        $bands  = ReportBuilder::create('r')
            ->columns([Column::make('v', 'V', 10.0, 100.0)->margin(4.0, 4.0)])
            ->rows([['v' => 'x']])
            ->build()->getBandInstances();
        $el = $bands[1]->getElements()[0];

        $this->assertSame(14.0, $el->getX());
        $this->assertSame(92.0, $el->getWidth());
    }

    public function testAsymmetricMargin(): void
    {
        $bands = ReportBuilder::create('r')
            ->columns([Column::make('v', 'V', 0.0, 100.0)->margin(6.0, 2.0)])
            ->rows([['v' => 'x']])
            ->build()->getBandInstances();
        $el = $bands[1]->getElements()[0];

        $this->assertSame(6.0,  $el->getX());
        $this->assertSame(92.0, $el->getWidth());
    }

    public function testMarginAppliedToHeaderAndAggregateToo(): void
    {
        $bands = ReportBuilder::create('r')
            ->columns([Column::make('v', 'V', 0.0, 100.0)->sum()->margin(5.0, 5.0)])
            ->rows([['v' => 10.0]])
            ->build()->getBandInstances();

        // col_hdr, detail, summary — all should have x=5, width=90
        foreach ($bands as $band) {
            $this->assertSame(5.0,  $band->getElements()[0]->getX());
            $this->assertSame(90.0, $band->getElements()[0]->getWidth());
        }
    }

    // ── Aggregate functions ───────────────────────────────────────────────────

    private function aggBuilder(string $fn): ReportBuilder
    {
        return ReportBuilder::create('r')
            ->columns([
                Column::make('lbl', 'Label', 0.0,   100.0),
                Column::make('val', 'Value', 100.0, 100.0)->{$fn}(),
            ]);
    }

    public function testAvgAcrossFlatRows(): void
    {
        $bands = $this->aggBuilder('avg')
            ->rows([['lbl' => 'a', 'val' => 10.0], ['lbl' => 'b', 'val' => 20.0]])
            ->build()->getBandInstances();

        $summary = $bands[count($bands) - 1];
        $this->assertSame('15', $this->val($summary, 100.0));
    }

    public function testMinAcrossFlatRows(): void
    {
        $bands = $this->aggBuilder('min')
            ->rows([['lbl' => 'a', 'val' => 30.0], ['lbl' => 'b', 'val' => 10.0], ['lbl' => 'c', 'val' => 20.0]])
            ->build()->getBandInstances();

        $summary = $bands[count($bands) - 1];
        $this->assertSame('10', $this->val($summary, 100.0));
    }

    public function testMaxAcrossFlatRows(): void
    {
        $bands = $this->aggBuilder('max')
            ->rows([['lbl' => 'a', 'val' => 30.0], ['lbl' => 'b', 'val' => 10.0], ['lbl' => 'c', 'val' => 20.0]])
            ->build()->getBandInstances();

        $summary = $bands[count($bands) - 1];
        $this->assertSame('30', $this->val($summary, 100.0));
    }

    public function testCountAcrossFlatRows(): void
    {
        $bands = $this->aggBuilder('count')
            ->rows([['lbl' => 'a', 'val' => 1.0], ['lbl' => 'b', 'val' => 2.0], ['lbl' => 'c', 'val' => 3.0]])
            ->build()->getBandInstances();

        $summary = $bands[count($bands) - 1];
        $this->assertSame('3', $this->val($summary, 100.0));
    }

    public function testAvgInGroupFooterUsesOnlyGroupRows(): void
    {
        $bands = ReportBuilder::create('r')
            ->columns([
                Column::make('cat', 'Cat', 0.0,   100.0),
                Column::make('val', 'Val', 100.0, 100.0)->avg(),
            ])
            ->rows([
                ['cat' => 'A', 'val' => 10.0],
                ['cat' => 'A', 'val' => 20.0],
                ['cat' => 'B', 'val' => 100.0],
            ])
            ->groupBy('cat')
            ->build()->getBandInstances();

        // Band sequence: col_hdr, grp_hdr A, detail, detail, grp_ftr A, grp_hdr B, detail, grp_ftr B, summary
        $grpFtrA = $bands[4];
        $this->assertSame('15', $this->val($grpFtrA, 100.0));
    }

    public function testZeroRowsProducesZeroAggregate(): void
    {
        foreach (['sum', 'avg', 'min', 'max', 'count'] as $fn) {
            $bands   = $this->aggBuilder($fn)->build()->getBandInstances();
            $summary = $bands[count($bands) - 1];
            $this->assertSame('0', $this->val($summary, 100.0), "Failed for fn={$fn}");
        }
    }
}
