<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Reports\DataSource\SqliteSalesByCategoryItemProvider;
use ReportWriter\App\Reports\SalesByCategoryItemFiller;
use ReportWriter\Instance\ReportInstance;

final class SalesByCategoryItemFillerTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/coffee-shop-mini.sql';
    private const SCHEMA  = __DIR__ . '/../../../database/schema.sql';

    public function testFillProducesReportInstanceWithExpectedIdAndBands(): void
    {
        $instance = $this->fill(['date' => '2026-08-22']);

        $this->assertInstanceOf(ReportInstance::class, $instance);
        $this->assertSame('sales-by-category-item', $instance->getReportInstanceId());

        $bands = $instance->getBandInstances();
        $this->assertNotEmpty($bands);

        // Expected band sequence for the fixture with one groupBy('category_name'):
        //   title, col-header, group-header(Coffee), detail(Latte), detail(Espresso),
        //   group-footer(Coffee), group-header(Pastry), detail(Croissant),
        //   detail(Muffin), group-footer(Pastry), summary
        $types = array_map(fn ($b) => $b->getBandType(), $bands);
        $this->assertSame(
            [
                'title',
                'col-header',
                'group-header', 'detail', 'detail', 'group-footer',
                'group-header', 'detail', 'detail', 'group-footer',
                'summary',
            ],
            $types
        );
    }

    public function testFillProducesTitleWithDateInterpolated(): void
    {
        $instance = $this->fill(['date' => '2026-08-22']);
        $titleBand = $instance->getBandInstances()[0];
        $titleText = $titleBand->getElements()[0]->getContent()->getValue();
        $this->assertStringContainsString('2026-08-22', $titleText);
        $this->assertStringContainsString('Sales by Category', $titleText);
    }

    public function testFillProducesFewerBandsWhenNoDataForDate(): void
    {
        $empty = $this->fill(['date' => '2020-01-01']);
        $full  = $this->fill(['date' => '2026-08-22']);

        $this->assertLessThan(
            count($full->getBandInstances()),
            count($empty->getBandInstances()),
            'empty-date report must have fewer bands than populated-date report'
        );
    }

    /** @param array<string, mixed> $params */
    private function fill(array $params): ReportInstance
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(self::SCHEMA);
        $pdo->exec((string) file_get_contents(self::FIXTURE));
        return (new SalesByCategoryItemFiller(new SqliteSalesByCategoryItemProvider($pdo)))->fill($params);
    }
}
