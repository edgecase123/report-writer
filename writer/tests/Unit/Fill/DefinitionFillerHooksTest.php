<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Fill;

use ReportWriter\Fill\BandContext;
use ReportWriter\Fill\DefinitionFiller;
use ReportWriter\Instance\BandInstance;
use ReportWriter\Instance\ReportInstance;
use ReportWriter\Interfaces\ReportDataSourceInterface;
use ReportWriter\Registry\DataSourceRegistry;
use ReportWriter\Registry\FormatterRegistry;
use ReportWriter\Template\TemplateLoader;
use PHPUnit\Framework\TestCase;

/**
 * Covers the extension hooks API on DefinitionFiller:
 *  - retrofit: onBand is truly immutable-fluent (returns `: self`, clones)
 *  - beforeFill: receives params, chains, throws propagate
 *  - afterFill:  receives ReportInstance, chains, throws propagate
 */
class DefinitionFillerHooksTest extends TestCase
{
    private TemplateLoader $loader;
    private FormatterRegistry $formatters;

    protected function setUp(): void
    {
        $this->loader     = new TemplateLoader();
        $this->formatters = FormatterRegistry::defaults();
    }

    private function makeSource(array $rows): ReportDataSourceInterface
    {
        return new class($rows) implements ReportDataSourceInterface {
            private array $rows;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function fetchRows(array $params): array { return $this->rows; }
        };
    }

    private function filler(array $templateData, array $rows): DefinitionFiller
    {
        $registry = new DataSourceRegistry();
        $registry->register($templateData['data_source'], $this->makeSource($rows));
        return new DefinitionFiller(
            $this->loader->loadFromArray($templateData),
            $registry,
            $this->formatters
        );
    }

    private function detailTemplate(): array
    {
        return [
            'report_definition_id' => 'test',
            'data_source'          => 'src',
            'bands'                => [
                [
                    'id' => 'detail', 'type' => 'detail',
                    'elements' => [
                        ['id' => 'name', 'x' => 0, 'width' => 100, 'height' => 12,
                         'content' => ['type' => 'field', 'field' => 'name']],
                    ],
                ],
            ],
        ];
    }

    // ── onBand immutability retrofit ─────────────────────────────────────────

    public function testOnBandReturnsCloneNotSelf(): void
    {
        $a = $this->filler($this->detailTemplate(), [['name' => 'x']]);
        $b = $a->onBand('detail', fn ($band, $ctx) => $band);

        $this->assertNotSame($a, $b, 'onBand must return a clone, not $this');
        $this->assertInstanceOf(DefinitionFiller::class, $b);
    }

    public function testOnBandDoesNotMutateOriginal(): void
    {
        $a = $this->filler($this->detailTemplate(), [['name' => 'x']]);
        // Register a suppress-all callback on the clone.
        $b = $a->onBand('detail', fn ($band, $ctx) => null);

        // Original filler must still emit the detail band — its bandCallbacks
        // property is unchanged because the clone got the mutation.
        $aBands = $a->fill([])->getBandInstances();
        $this->assertCount(1, $aBands, 'Original filler must be unaffected by mutation on the clone');

        // Clone suppresses.
        $bBands = $b->fill([])->getBandInstances();
        $this->assertCount(0, $bBands, 'Clone must reflect the registered suppress callback');
    }

    public function testFireAndForgetOnBandSilentlyDropsCallback(): void
    {
        $filler = $this->filler($this->detailTemplate(), [['name' => 'x']]);
        // Discarding the return of onBand loses the registration — this is the
        // load-bearing consequence of immutable-fluent semantics.
        $filler->onBand('detail', fn ($band, $ctx) => null);

        $bands = $filler->fill([])->getBandInstances();
        $this->assertCount(1, $bands, 'Discarded clone must not affect original');
    }

    // ── beforeFill ───────────────────────────────────────────────────────────

    public function testBeforeFillReceivesParams(): void
    {
        $seen = null;
        $filler = $this->filler($this->detailTemplate(), [['name' => 'x']])
            ->beforeFill(function (array $params) use (&$seen): array {
                $seen = $params;
                return $params;
            });

        $filler->fill(['foo' => 'bar']);
        $this->assertSame(['foo' => 'bar'], $seen);
    }

    public function testBeforeFillTransformationFlowsToParamValidation(): void
    {
        $tmpl = $this->detailTemplate();
        $tmpl['params'] = ['courseId' => ['type' => 'int', 'required' => true]];

        // Callback injects the required param — validation should now pass.
        $filler = $this->filler($tmpl, [['name' => 'x']])
            ->beforeFill(fn (array $params): array => $params + ['courseId' => 42]);

        $instance = $filler->fill([]);
        $this->assertInstanceOf(ReportInstance::class, $instance);
    }

    public function testBeforeFillChainsInRegistrationOrder(): void
    {
        $order = [];
        $filler = $this->filler($this->detailTemplate(), [['name' => 'x']])
            ->beforeFill(function (array $p) use (&$order): array {
                $order[] = 'first';
                $p['seen'] = ($p['seen'] ?? '') . 'A';
                return $p;
            })
            ->beforeFill(function (array $p) use (&$order): array {
                $order[] = 'second';
                $p['seen'] = ($p['seen'] ?? '') . 'B';
                return $p;
            });

        // Third callback observes the concatenation from the first two.
        $observed = null;
        $filler = $filler->beforeFill(function (array $p) use (&$observed): array {
            $observed = $p['seen'] ?? null;
            return $p;
        });

        $filler->fill([]);
        $this->assertSame(['first', 'second'], $order);
        $this->assertSame('AB', $observed);
    }

    public function testBeforeFillReturnsCloneNotSelf(): void
    {
        $a = $this->filler($this->detailTemplate(), [['name' => 'x']]);
        $b = $a->beforeFill(fn (array $p): array => $p);
        $this->assertNotSame($a, $b);
    }

    public function testBeforeFillThrowingPropagates(): void
    {
        $filler = $this->filler($this->detailTemplate(), [['name' => 'x']])
            ->beforeFill(function (array $p): array {
                throw new \RuntimeException('kaboom');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('kaboom');
        $filler->fill([]);
    }

    // ── afterFill ────────────────────────────────────────────────────────────

    public function testAfterFillReceivesReportInstance(): void
    {
        $seen = null;
        $filler = $this->filler($this->detailTemplate(), [['name' => 'x']])
            ->afterFill(function (ReportInstance $inst) use (&$seen): ReportInstance {
                $seen = $inst;
                return $inst;
            });

        $result = $filler->fill([]);
        $this->assertInstanceOf(ReportInstance::class, $seen);
        $this->assertSame($seen, $result);
    }

    public function testAfterFillTransformationFlowsThrough(): void
    {
        $filler = $this->filler($this->detailTemplate(), [['name' => 'x']])
            ->afterFill(function (ReportInstance $inst): ReportInstance {
                // Return an entirely new instance with no bands.
                return new ReportInstance($inst->getReportInstanceId(), []);
            });

        $instance = $filler->fill([]);
        $this->assertCount(0, $instance->getBandInstances());
    }

    public function testAfterFillChainsInRegistrationOrder(): void
    {
        $filler = $this->filler($this->detailTemplate(), [['name' => 'x']])
            ->afterFill(fn (ReportInstance $inst): ReportInstance
                => new ReportInstance('first-' . $inst->getReportInstanceId(), $inst->getBandInstances()))
            ->afterFill(fn (ReportInstance $inst): ReportInstance
                => new ReportInstance('second-' . $inst->getReportInstanceId(), $inst->getBandInstances()));

        $instance = $filler->fill([]);
        $this->assertSame('second-first-test', $instance->getReportInstanceId());
    }

    public function testAfterFillReturnsCloneNotSelf(): void
    {
        $a = $this->filler($this->detailTemplate(), [['name' => 'x']]);
        $b = $a->afterFill(fn (ReportInstance $inst): ReportInstance => $inst);
        $this->assertNotSame($a, $b);
    }

    public function testAfterFillThrowingPropagates(): void
    {
        $filler = $this->filler($this->detailTemplate(), [['name' => 'x']])
            ->afterFill(function (ReportInstance $inst): ReportInstance {
                throw new \RuntimeException('boom');
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $filler->fill([]);
    }

    public function testAfterFillTypeErrorWhenCallbackReturnsNull(): void
    {
        // Non-nullable return type; TypeError on the reducer step that
        // consumes the null result.
        $filler = $this->filler($this->detailTemplate(), [['name' => 'x']])
            ->afterFill(function (ReportInstance $inst) {
                return null;
            });

        $this->expectException(\TypeError::class);
        $filler->fill([]);
    }
}
