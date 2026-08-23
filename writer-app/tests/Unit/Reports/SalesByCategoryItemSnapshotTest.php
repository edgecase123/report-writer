<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Reports\DataSource\SqliteSalesByCategoryItemProvider;
use ReportWriter\App\Reports\SalesByCategoryItemFiller;
use ReportWriter\App\Tests\Support\SnapshotAssertions;
use ReportWriter\Layout\Flattener;
use ReportWriter\Layout\LayoutService;
use ReportWriter\Layout\PageConfig;
use ReportWriter\Renderer\HtmlRenderer;

final class SalesByCategoryItemSnapshotTest extends TestCase
{
    use SnapshotAssertions;

    private const FIXTURE  = __DIR__ . '/../../Fixtures/coffee-shop-mini.sql';
    private const SCHEMA   = __DIR__ . '/../../../database/schema.sql';
    private const SNAPSHOT = __DIR__ . '/../../Snapshots/sales-by-category-item.html';

    public function testRendersByteForByteMatchOfSnapshotOnFixture(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(self::SCHEMA);
        $pdo->exec((string) file_get_contents(self::FIXTURE));

        $filler   = new SalesByCategoryItemFiller(new SqliteSalesByCategoryItemProvider($pdo));
        $instance = $filler->fill(['date' => '2026-08-22']);

        $page   = new PageConfig();
        $stream = (new LayoutService(new Flattener(), $page))->layout($instance);
        $html   = (new HtmlRenderer($page))->render($stream);

        $this->assertSnapshotMatches($html, self::SNAPSHOT);
    }
}
