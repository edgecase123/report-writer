<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Fill;

use ReportWriter\Fill\DefinitionFiller;
use ReportWriter\Interfaces\ReportDataSourceInterface;
use ReportWriter\Registry\DataSourceRegistry;
use ReportWriter\Registry\FormatterRegistry;
use ReportWriter\Template\TemplateLoader;
use PHPUnit\Framework\TestCase;

class DefinitionFillerTest extends TestCase
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

    private function makeRegistry(array $sourceMap): DataSourceRegistry
    {
        $registry = new DataSourceRegistry();
        foreach ($sourceMap as $name => $rows) {
            $registry->register($name, $this->makeSource($rows));
        }
        return $registry;
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

    private function baseTemplate(): array
    {
        return [
            'report_definition_id' => 'test',
            'data_source'          => 'ignored',
            'bands'                => [],
        ];
    }

    // ── Static bands ──────────────────────────────────────────────────────────

    public function testStaticBandEmittedOnce(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'title', 'type' => 'title',
            'elements' => [
                ['id' => 't', 'x' => 0, 'width' => 200, 'height' => 20,
                 'content' => ['type' => 'text', 'value' => 'My Report']],
            ],
        ];

        $instance = $this->filler($tmpl, [])->fill([]);
        $types    = array_map(fn ($b) => $b->getBandType(), $instance->getBandInstances());

        $this->assertSame(['title'], $types);
    }

    public function testTextContentInterpolatesParams(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'title', 'type' => 'title',
            'elements' => [
                ['id' => 't', 'x' => 0, 'width' => 200, 'height' => 20,
                 'content' => ['type' => 'text', 'value' => 'Report for {courseId}']],
            ],
        ];

        $instance = $this->filler($tmpl, [])->fill(['courseId' => 42]);
        $value    = $instance->getBandInstances()[0]->getElements()[0]->getContent()->getValue();

        $this->assertSame('Report for 42', $value);
    }

    // ── Detail bands ──────────────────────────────────────────────────────────

    public function testDetailRepeatsPerRow(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'detail', 'type' => 'detail',
            'elements' => [
                ['id' => 'name', 'x' => 0, 'width' => 100, 'height' => 12,
                 'content' => ['type' => 'field', 'field' => 'name']],
            ],
        ];

        $instance = $this->filler($tmpl, [
            ['name' => 'Alpha'],
            ['name' => 'Beta'],
            ['name' => 'Gamma'],
        ])->fill([]);

        $detailBands = array_filter($instance->getBandInstances(), fn ($b) => $b->getBandType() === 'detail');
        $this->assertCount(3, $detailBands);
    }

    public function testFieldContentResolvesFromRow(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'detail', 'type' => 'detail',
            'elements' => [
                ['id' => 'amount', 'x' => 0, 'width' => 80, 'height' => 12,
                 'content' => ['type' => 'field', 'field' => 'amount', 'format' => 'currency']],
            ],
        ];

        $instance = $this->filler($tmpl, [['amount' => 12.5]])->fill([]);
        $value    = $instance->getBandInstances()[0]->getElements()[0]->getContent()->getValue();

        $this->assertSame('$12.50', $value);
    }

    // ── Aggregate bands ───────────────────────────────────────────────────────

    public function testSummaryAggregatesAllRows(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'summary', 'type' => 'summary',
            'elements' => [
                ['id' => 'total', 'x' => 0, 'width' => 80, 'height' => 15,
                 'content' => ['type' => 'aggregate', 'field' => 'amount', 'fn' => 'sum', 'format' => 'currency']],
            ],
        ];

        $instance = $this->filler($tmpl, [
            ['amount' => 10.0],
            ['amount' => 30.0],
        ])->fill([]);

        $value = $instance->getBandInstances()[0]->getElements()[0]->getContent()->getValue();
        $this->assertSame('$40.00', $value);
    }

    public function testSummaryZeroWithNoRows(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'summary', 'type' => 'summary',
            'elements' => [
                ['id' => 'total', 'x' => 0, 'width' => 80, 'height' => 15,
                 'content' => ['type' => 'aggregate', 'field' => 'amount', 'fn' => 'sum', 'format' => 'currency']],
            ],
        ];

        $value = $this->filler($tmpl, [])->fill([])->getBandInstances()[0]->getElements()[0]->getContent()->getValue();
        $this->assertSame('$0.00', $value);
    }

    // ── Grouped bands ─────────────────────────────────────────────────────────

    public function testGroupingProducesOneGroupHeaderPerGroup(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'grp_hdr', 'type' => 'group-header', 'group_by' => 'category',
            'elements' => [
                ['id' => 'gv', 'x' => 0, 'width' => 200, 'height' => 15,
                 'content' => ['type' => 'group_value']],
            ],
        ];
        $tmpl['bands'][] = [
            'id' => 'detail', 'type' => 'detail',
            'elements' => [
                ['id' => 'name', 'x' => 0, 'width' => 100, 'height' => 12,
                 'content' => ['type' => 'field', 'field' => 'name']],
            ],
        ];
        $tmpl['bands'][] = [
            'id' => 'grp_ftr', 'type' => 'group-footer',
            'elements' => [
                ['id' => 'total', 'x' => 0, 'width' => 80, 'height' => 15,
                 'content' => ['type' => 'aggregate', 'field' => 'amount', 'fn' => 'sum']],
            ],
        ];

        $instance = $this->filler($tmpl, [
            ['name' => 'Hat',      'category' => 'Apparel', 'amount' => 15.0],
            ['name' => 'Shirt',    'category' => 'Apparel', 'amount' => 25.0],
            ['name' => 'Tee Time', 'category' => 'Golf',    'amount' => 45.0],
        ])->fill([]);

        $bands = $instance->getBandInstances();
        $types = array_map(fn ($b) => $b->getBandType(), $bands);

        $this->assertSame(
            ['group-header', 'detail', 'detail', 'group-footer', 'group-header', 'detail', 'group-footer'],
            $types
        );
    }

    public function testGroupValueContentShowsGroupKey(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'grp_hdr', 'type' => 'group-header', 'group_by' => 'category',
            'elements' => [
                ['id' => 'gv', 'x' => 0, 'width' => 200, 'height' => 15,
                 'content' => ['type' => 'group_value']],
            ],
        ];
        $tmpl['bands'][] = [
            'id' => 'grp_ftr', 'type' => 'group-footer',
            'elements' => [
                ['id' => 'lbl', 'x' => 0, 'width' => 80, 'height' => 15,
                 'content' => ['type' => 'text', 'value' => 'Subtotal']],
            ],
        ];

        $instance  = $this->filler($tmpl, [['category' => 'Apparel', 'amount' => 10.0]])->fill([]);
        $groupHdr  = $instance->getBandInstances()[0];
        $groupValue = $groupHdr->getElements()[0]->getContent()->getValue();

        $this->assertSame('Apparel', $groupValue);
    }

    public function testGroupFooterAggregatesGroupRowsOnly(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'grp_hdr', 'type' => 'group-header', 'group_by' => 'category',
            'elements' => [
                ['id' => 'gv', 'x' => 0, 'width' => 200, 'height' => 15,
                 'content' => ['type' => 'group_value']],
            ],
        ];
        $tmpl['bands'][] = [
            'id' => 'grp_ftr', 'type' => 'group-footer',
            'elements' => [
                ['id' => 'total', 'x' => 0, 'width' => 80, 'height' => 15,
                 'content' => ['type' => 'aggregate', 'field' => 'amount', 'fn' => 'sum', 'format' => 'currency']],
            ],
        ];

        $instance = $this->filler($tmpl, [
            ['category' => 'A', 'amount' => 10.0],
            ['category' => 'A', 'amount' => 20.0],
            ['category' => 'B', 'amount' => 99.0],
        ])->fill([]);

        $bands      = $instance->getBandInstances();
        $aFooter    = $bands[1]; // group-footer for A
        $aTotal     = $aFooter->getElements()[0]->getContent()->getValue();

        $this->assertSame('$30.00', $aTotal);
    }

    // ── Param validation ─────────────────────────────────────────────────────

    public function testValidatesRequiredParam(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['params'] = ['courseId' => ['type' => 'int', 'required' => true]];

        $this->expectException(\InvalidArgumentException::class);
        $this->filler($tmpl, [])->fill([]);
    }

    public function testOptionalParamDoesNotThrow(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['params'] = ['courseId' => ['type' => 'int', 'required' => false]];

        $instance = $this->filler($tmpl, [])->fill([]);
        $this->assertNotNull($instance);
    }

    // ── Band callbacks ────────────────────────────────────────────────────────

    public function testCallbackCanSuppressDetailBand(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'detail', 'type' => 'detail',
            'elements' => [
                ['id' => 'amount', 'x' => 0, 'width' => 80, 'height' => 12,
                 'content' => ['type' => 'field', 'field' => 'amount']],
            ],
        ];

        $filler = $this->filler($tmpl, [
            ['amount' => 10],
            ['amount' => 0],
            ['amount' => 5],
        ]);
        $filler = $filler->onBand('detail', function ($band, $ctx) {
            return $ctx->getRow()['amount'] === 0 ? null : $band;
        });

        $bands = $filler->fill([])->getBandInstances();
        $this->assertCount(2, $bands);
    }

    public function testCallbackReceivesCorrectRowContext(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'detail', 'type' => 'detail',
            'elements' => [
                ['id' => 'name', 'x' => 0, 'width' => 100, 'height' => 12,
                 'content' => ['type' => 'field', 'field' => 'name']],
            ],
        ];

        $seen = [];
        $filler = $this->filler($tmpl, [['name' => 'Alpha'], ['name' => 'Beta']]);
        $filler = $filler->onBand('detail', function ($band, $ctx) use (&$seen) {
            $seen[] = $ctx->getRow()['name'];
            return $band;
        });

        $filler->fill([]);
        $this->assertSame(['Alpha', 'Beta'], $seen);
    }

    public function testCallbackReceivesGroupValueContext(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'grp_hdr', 'type' => 'group-header', 'group_by' => 'cat',
            'elements' => [
                ['id' => 'gv', 'x' => 0, 'width' => 100, 'height' => 12,
                 'content' => ['type' => 'group_value']],
            ],
        ];
        $tmpl['bands'][] = [
            'id' => 'grp_ftr', 'type' => 'group-footer',
            'elements' => [
                ['id' => 'lbl', 'x' => 0, 'width' => 80, 'height' => 12,
                 'content' => ['type' => 'text', 'value' => 'Total']],
            ],
        ];

        $seen = [];
        $filler = $this->filler($tmpl, [
            ['cat' => 'A', 'amount' => 1],
            ['cat' => 'B', 'amount' => 2],
        ]);
        $filler = $filler->onBand('grp_hdr', function ($band, $ctx) use (&$seen) {
            $seen[] = $ctx->getGroupValue();
            return $band;
        });

        $filler->fill([]);
        $this->assertSame(['A', 'B'], $seen);
    }

    public function testCallbacksChain(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'detail', 'type' => 'detail',
            'elements' => [
                ['id' => 'name', 'x' => 0, 'width' => 100, 'height' => 12,
                 'content' => ['type' => 'field', 'field' => 'name']],
            ],
        ];

        $log = [];
        $filler = $this->filler($tmpl, [['name' => 'X']]);
        $filler = $filler->onBand('detail', function ($band, $ctx) use (&$log) { $log[] = 'first'; return $band; });
        $filler = $filler->onBand('detail', function ($band, $ctx) use (&$log) { $log[] = 'second'; return null; });
        $filler = $filler->onBand('detail', function ($band, $ctx) use (&$log) { $log[] = 'third'; return $band; });

        $bands = $filler->fill([])->getBandInstances();
        $this->assertCount(0, $bands);
        $this->assertSame(['first', 'second'], $log, 'Chain stops at first null');
    }

    public function testCallbackCanSuppressStaticBand(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'title', 'type' => 'title',
            'elements' => [
                ['id' => 't', 'x' => 0, 'width' => 200, 'height' => 20,
                 'content' => ['type' => 'text', 'value' => 'Report']],
            ],
        ];

        $filler = $this->filler($tmpl, []);
        $filler = $filler->onBand('title', fn($band, $ctx) => null);

        $this->assertCount(0, $filler->fill([])->getBandInstances());
    }

    public function testCallbackReceivesAggregateRowsForSummary(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'summary', 'type' => 'summary',
            'elements' => [
                ['id' => 'total', 'x' => 0, 'width' => 80, 'height' => 12,
                 'content' => ['type' => 'aggregate', 'field' => 'amount', 'fn' => 'sum']],
            ],
        ];

        $rows = [['amount' => 10], ['amount' => 20]];
        $seen = [];
        $filler = $this->filler($tmpl, $rows);
        $filler = $filler->onBand('summary', function ($band, $ctx) use (&$seen) {
            $seen = $ctx->getAggregateRows();
            return $band;
        });

        $filler->fill([]);
        $this->assertCount(2, $seen);
    }

    // ── Multiple data sources ─────────────────────────────────────────────────

    public function testBandDataSourceOverridesTemplateDefault(): void
    {
        $tmpl = $this->baseTemplate(); // data_source = 'ignored'
        $tmpl['bands'][] = [
            'id' => 'detail', 'type' => 'detail',
            'data_source' => 'secondary',
            'elements' => [
                ['id' => 'name', 'x' => 0, 'width' => 100, 'height' => 12,
                 'content' => ['type' => 'field', 'field' => 'name']],
            ],
        ];

        $registry = $this->makeRegistry([
            'ignored'   => [],
            'secondary' => [['name' => 'Alpha'], ['name' => 'Beta']],
        ]);
        $filler = new DefinitionFiller(
            $this->loader->loadFromArray($tmpl),
            $registry,
            $this->formatters
        );

        $bands = $filler->fill([])->getBandInstances();
        $this->assertCount(2, $bands);
        $this->assertSame('Alpha', $bands[0]->getElements()[0]->getContent()->getValue());
        $this->assertSame('Beta',  $bands[1]->getElements()[0]->getContent()->getValue());
    }

    public function testBandsFromDifferentSourcesCoexist(): void
    {
        $tmpl = $this->baseTemplate(); // data_source = 'ignored'
        $tmpl['bands'][] = [
            'id' => 'title', 'type' => 'title',
            'elements' => [
                ['id' => 't', 'x' => 0, 'width' => 200, 'height' => 20,
                 'content' => ['type' => 'text', 'value' => 'Report']],
            ],
        ];
        $tmpl['bands'][] = [
            'id' => 'detail', 'type' => 'detail',
            'data_source' => 'items',
            'elements' => [
                ['id' => 'name', 'x' => 0, 'width' => 100, 'height' => 12,
                 'content' => ['type' => 'field', 'field' => 'name']],
            ],
        ];

        $registry = $this->makeRegistry([
            'ignored' => [],
            'items'   => [['name' => 'One'], ['name' => 'Two'], ['name' => 'Three']],
        ]);
        $filler = new DefinitionFiller(
            $this->loader->loadFromArray($tmpl),
            $registry,
            $this->formatters
        );

        $bands = $filler->fill([])->getBandInstances();
        $types = array_map(fn ($b) => $b->getBandType(), $bands);
        $this->assertSame(['title', 'detail', 'detail', 'detail'], $types);
    }

    public function testSameSourceFetchedOnlyOnce(): void
    {
        $counter = new \stdClass();
        $counter->calls = 0;
        $source = new class($counter) implements ReportDataSourceInterface {
            private \stdClass $counter;
            public function __construct(\stdClass $counter) { $this->counter = $counter; }
            public function fetchRows(array $params): array { $this->counter->calls++; return [['n' => 'x']]; }
        };

        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = ['id' => 'b1', 'type' => 'title', 'elements' => [
            ['id' => 't', 'x' => 0, 'width' => 100, 'height' => 10,
             'content' => ['type' => 'text', 'value' => 'A']],
        ]];
        $tmpl['bands'][] = ['id' => 'b2', 'type' => 'detail', 'elements' => [
            ['id' => 'n', 'x' => 0, 'width' => 100, 'height' => 10,
             'content' => ['type' => 'field', 'field' => 'n']],
        ]];

        $registry = new DataSourceRegistry();
        $registry->register('ignored', $source);
        $filler = new DefinitionFiller($this->loader->loadFromArray($tmpl), $registry, $this->formatters);

        $filler->fill([]);
        $this->assertSame(1, $counter->calls, 'Same source must be fetched only once per fill()');
    }

    // ── Element IDs ───────────────────────────────────────────────────────────

    public function testElementIdsAreUniqueAcrossDetailRows(): void
    {
        $tmpl = $this->baseTemplate();
        $tmpl['bands'][] = [
            'id' => 'detail', 'type' => 'detail',
            'elements' => [
                ['id' => 'name', 'x' => 0, 'width' => 100, 'height' => 12,
                 'content' => ['type' => 'field', 'field' => 'name']],
            ],
        ];

        $instance = $this->filler($tmpl, [['name' => 'A'], ['name' => 'B']])->fill([]);
        $ids      = [];
        foreach ($instance->getBandInstances() as $band) {
            foreach ($band->getElements() as $el) {
                $ids[] = $el->getInstanceId();
            }
        }

        $this->assertCount(count(array_unique($ids)), $ids, 'Element IDs must be unique');
    }
}
