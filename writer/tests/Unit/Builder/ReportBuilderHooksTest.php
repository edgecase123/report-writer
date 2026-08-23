<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Builder;

use ReportWriter\Builder\Column;
use ReportWriter\Builder\ReportBuilder;
use ReportWriter\Instance\BandInstance;
use ReportWriter\Instance\ReportInstance;
use PHPUnit\Framework\TestCase;

/**
 * Covers the extension hooks API on ReportBuilder:
 *  - beforeBuild: receives rows array, chains, throws propagate
 *  - afterBuild:  receives ReportInstance, chains, throws propagate
 *  - onBand:      band-type keyed, chains, null suppresses, immutable
 */
class ReportBuilderHooksTest extends TestCase
{
    private function itemCol(): Column
    {
        return Column::make('item', 'Item', 0.0, 200.0);
    }

    private function totalCol(): Column
    {
        return Column::make('total', 'Total', 200.0, 100.0);
    }

    private function baseBuilder(): ReportBuilder
    {
        return ReportBuilder::create('test_report')
            ->columns([$this->itemCol(), $this->totalCol()]);
    }

    private function row(string $item, float $total, string $category = ''): array
    {
        return ['item' => $item, 'total' => $total, 'category' => $category];
    }

    private function bandTypes(ReportInstance $inst): array
    {
        return array_map(fn (BandInstance $b) => $b->getBandType(), $inst->getBandInstances());
    }

    // ── beforeBuild ──────────────────────────────────────────────────────────

    public function testBeforeBuildReceivesRows(): void
    {
        $seen   = null;
        $rows   = [$this->row('a', 1.0), $this->row('b', 2.0)];
        $result = $this->baseBuilder()
            ->rows($rows)
            ->beforeBuild(function (array $r) use (&$seen): array {
                $seen = $r;
                return $r;
            })
            ->build();

        $this->assertSame($rows, $seen);
        $this->assertInstanceOf(ReportInstance::class, $result);
    }

    public function testBeforeBuildTransformationFlowsIntoDetailBands(): void
    {
        $instance = $this->baseBuilder()
            ->rows([$this->row('a', 1.0), $this->row('b', 2.0), $this->row('c', 3.0)])
            // Filter out one row before build sees it.
            ->beforeBuild(fn (array $rows): array
                => array_values(array_filter($rows, fn ($r) => $r['item'] !== 'b')))
            ->build();

        $detailBands = array_filter(
            $instance->getBandInstances(),
            fn (BandInstance $b) => $b->getBandType() === 'detail'
        );
        $this->assertCount(2, $detailBands);
    }

    public function testBeforeBuildChainsInRegistrationOrder(): void
    {
        $order = [];
        $builder = $this->baseBuilder()
            ->rows([$this->row('a', 1.0)])
            ->beforeBuild(function (array $r) use (&$order): array {
                $order[] = 'first';
                $r[] = $this->row('added-by-first', 10.0);
                return $r;
            })
            ->beforeBuild(function (array $r) use (&$order): array {
                $order[] = 'second';
                // The second callback observes the row added by the first.
                $items = array_column($r, 'item');
                $order[] = 'items:' . implode(',', $items);
                return $r;
            });

        $builder->build();
        $this->assertSame(['first', 'second', 'items:a,added-by-first'], $order);
    }

    public function testBeforeBuildReturnsCloneNotSelf(): void
    {
        $a = $this->baseBuilder();
        $b = $a->beforeBuild(fn (array $r): array => $r);
        $this->assertNotSame($a, $b);
    }

    public function testBeforeBuildThrowingPropagates(): void
    {
        $builder = $this->baseBuilder()
            ->rows([$this->row('a', 1.0)])
            ->beforeBuild(function (array $r): array {
                throw new \RuntimeException('kaboom');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('kaboom');
        $builder->build();
    }

    // ── afterBuild ───────────────────────────────────────────────────────────

    public function testAfterBuildReceivesReportInstance(): void
    {
        $seen   = null;
        $result = $this->baseBuilder()
            ->rows([$this->row('a', 1.0)])
            ->afterBuild(function (ReportInstance $inst) use (&$seen): ReportInstance {
                $seen = $inst;
                return $inst;
            })
            ->build();

        $this->assertInstanceOf(ReportInstance::class, $seen);
        $this->assertSame($seen, $result);
    }

    public function testAfterBuildTransformationFlowsThrough(): void
    {
        $result = $this->baseBuilder()
            ->rows([$this->row('a', 1.0)])
            ->afterBuild(fn (ReportInstance $inst): ReportInstance
                => new ReportInstance('replaced', []))
            ->build();

        $this->assertSame('replaced', $result->getReportInstanceId());
        $this->assertCount(0, $result->getBandInstances());
    }

    public function testAfterBuildChainsInRegistrationOrder(): void
    {
        $result = $this->baseBuilder()
            ->rows([$this->row('a', 1.0)])
            ->afterBuild(fn (ReportInstance $inst): ReportInstance
                => new ReportInstance('first-' . $inst->getReportInstanceId(), $inst->getBandInstances()))
            ->afterBuild(fn (ReportInstance $inst): ReportInstance
                => new ReportInstance('second-' . $inst->getReportInstanceId(), $inst->getBandInstances()))
            ->build();

        $this->assertSame('second-first-test_report', $result->getReportInstanceId());
    }

    public function testAfterBuildReturnsCloneNotSelf(): void
    {
        $a = $this->baseBuilder();
        $b = $a->afterBuild(fn (ReportInstance $inst): ReportInstance => $inst);
        $this->assertNotSame($a, $b);
    }

    public function testAfterBuildThrowingPropagates(): void
    {
        $builder = $this->baseBuilder()
            ->rows([$this->row('a', 1.0)])
            ->afterBuild(function (ReportInstance $inst): ReportInstance {
                throw new \RuntimeException('boom');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $builder->build();
    }

    public function testAfterBuildTypeErrorWhenCallbackReturnsNull(): void
    {
        $builder = $this->baseBuilder()
            ->rows([$this->row('a', 1.0)])
            ->afterBuild(function (ReportInstance $inst) {
                return null;
            });

        $this->expectException(\TypeError::class);
        $builder->build();
    }

    // ── onBand ───────────────────────────────────────────────────────────────

    public function testOnBandReturnsCloneNotSelf(): void
    {
        $a = $this->baseBuilder();
        $b = $a->onBand('detail', fn ($band, $ctx) => $band);
        $this->assertNotSame($a, $b);
    }

    public function testOnBandFiresPerBandTypeForAllDetailRows(): void
    {
        $seen = [];
        $this->baseBuilder()
            ->rows([$this->row('a', 1.0), $this->row('b', 2.0), $this->row('c', 3.0)])
            ->onBand('detail', function ($band, $ctx) use (&$seen) {
                $seen[] = $ctx->getRow()['item'];
                return $band;
            })
            ->build();

        // Single registration keyed by band-type — fires once per detail row.
        $this->assertSame(['a', 'b', 'c'], $seen);
    }

    public function testOnBandNullSuppressesDetailBand(): void
    {
        $instance = $this->baseBuilder()
            ->rows([$this->row('a', 1.0), $this->row('b', 2.0), $this->row('c', 3.0)])
            ->onBand('detail', fn ($band, $ctx) => $ctx->getRow()['item'] === 'b' ? null : $band)
            ->build();

        $detailBands = array_filter(
            $instance->getBandInstances(),
            fn (BandInstance $b) => $b->getBandType() === 'detail'
        );
        $this->assertCount(2, $detailBands);
    }

    public function testOnBandNullSuppressesTitleBand(): void
    {
        $instance = $this->baseBuilder()
            ->title('My Report')
            ->rows([$this->row('a', 1.0)])
            ->onBand('title', fn ($band, $ctx) => null)
            ->build();

        $this->assertNotContains('title', $this->bandTypes($instance));
    }

    public function testOnBandNullSuppressesSummaryBand(): void
    {
        $instance = $this->baseBuilder()
            ->rows([$this->row('a', 1.0)])
            ->onBand('summary', fn ($band, $ctx) => null)
            ->build();

        $this->assertNotContains('summary', $this->bandTypes($instance));
    }

    public function testOnBandNullSuppressesGroupHeaderAndFooter(): void
    {
        $instance = $this->baseBuilder()
            ->rows([
                $this->row('a', 1.0, 'X'),
                $this->row('b', 2.0, 'Y'),
            ])
            ->groupBy('category')
            ->onBand('group-header', fn ($band, $ctx) => null)
            ->onBand('group-footer', fn ($band, $ctx) => null)
            ->build();

        $types = $this->bandTypes($instance);
        $this->assertNotContains('group-header', $types);
        $this->assertNotContains('group-footer', $types);
    }

    public function testOnBandGroupHeaderContextCarriesGroupValueAndRows(): void
    {
        $captures = [];
        $this->baseBuilder()
            ->rows([
                $this->row('a', 1.0, 'X'),
                $this->row('b', 2.0, 'X'),
                $this->row('c', 3.0, 'Y'),
            ])
            ->groupBy('category')
            ->onBand('group-header', function ($band, $ctx) use (&$captures) {
                $captures[] = [
                    'groupValue' => $ctx->getGroupValue(),
                    'rowCount'   => count($ctx->getAggregateRows()),
                ];
                return $band;
            })
            ->build();

        $this->assertSame(
            [
                ['groupValue' => 'X', 'rowCount' => 2],
                ['groupValue' => 'Y', 'rowCount' => 1],
            ],
            $captures
        );
    }

    public function testOnBandCallbacksChainInRegistrationOrder(): void
    {
        $log = [];
        $instance = $this->baseBuilder()
            ->rows([$this->row('a', 1.0)])
            ->onBand('detail', function ($band, $ctx) use (&$log) { $log[] = 'first'; return $band; })
            ->onBand('detail', function ($band, $ctx) use (&$log) { $log[] = 'second'; return null; })
            ->onBand('detail', function ($band, $ctx) use (&$log) { $log[] = 'third'; return $band; })
            ->build();

        $this->assertSame(['first', 'second'], $log, 'Chain stops at first null');
        $detailBands = array_filter(
            $instance->getBandInstances(),
            fn (BandInstance $b) => $b->getBandType() === 'detail'
        );
        $this->assertCount(0, $detailBands);
    }

    public function testOnBandThrowingPropagates(): void
    {
        $builder = $this->baseBuilder()
            ->rows([$this->row('a', 1.0)])
            ->onBand('detail', function ($band, $ctx): BandInstance {
                throw new \RuntimeException('bandboom');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bandboom');
        $builder->build();
    }

    public function testOnBandUnregisteredTypeIsNoOp(): void
    {
        // Registering for a band-type that never appears (no title set)
        // must not error and must not affect other bands.
        $instance = $this->baseBuilder()
            ->rows([$this->row('a', 1.0)])
            ->onBand('title', fn ($band, $ctx) => null)
            ->build();

        // Detail + col-header + summary still present; no title band existed.
        $types = $this->bandTypes($instance);
        $this->assertContains('col-header', $types);
        $this->assertContains('detail', $types);
        $this->assertContains('summary', $types);
    }

    public function testOnBandDoesNotMutateOriginalBuilder(): void
    {
        $a = $this->baseBuilder()->rows([$this->row('a', 1.0)]);
        $b = $a->onBand('detail', fn ($band, $ctx) => null);

        $aBands = array_filter(
            $a->build()->getBandInstances(),
            fn (BandInstance $band) => $band->getBandType() === 'detail'
        );
        $bBands = array_filter(
            $b->build()->getBandInstances(),
            fn (BandInstance $band) => $band->getBandType() === 'detail'
        );

        $this->assertCount(1, $aBands, 'Original builder unaffected');
        $this->assertCount(0, $bBands, 'Clone carries the suppression');
    }
}
