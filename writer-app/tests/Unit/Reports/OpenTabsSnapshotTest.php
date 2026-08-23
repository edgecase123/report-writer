<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Reports\DataSource\SqliteOpenTabsProvider;
use ReportWriter\App\Reports\JsonTemplateRepository;
use ReportWriter\App\Tests\Support\SnapshotAssertions;
use ReportWriter\Fill\DefinitionFillerFactory;
use ReportWriter\Layout\Flattener;
use ReportWriter\Layout\LayoutService;
use ReportWriter\Layout\PageConfig;
use ReportWriter\Registry\DataSourceRegistry;
use ReportWriter\Registry\FormatterRegistry;
use ReportWriter\Renderer\HtmlRenderer;
use ReportWriter\Template\TemplateLoader;

final class OpenTabsSnapshotTest extends TestCase
{
    use SnapshotAssertions;

    private const FIXTURE       = __DIR__ . '/../../Fixtures/coffee-shop-mini.sql';
    private const SCHEMA        = __DIR__ . '/../../../database/schema.sql';
    private const TEMPLATES_DIR = __DIR__ . '/../../../templates';
    private const SNAPSHOT      = __DIR__ . '/../../Snapshots/open-tabs.html';

    public function testRendersByteForByteMatchOfSnapshotOnFixture(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(self::SCHEMA);
        $pdo->exec((string) file_get_contents(self::FIXTURE));

        $registry = new DataSourceRegistry();
        $registry->register('open-tabs', new SqliteOpenTabsProvider($pdo));

        $factory = new DefinitionFillerFactory($registry, FormatterRegistry::defaults());
        $repo    = new JsonTemplateRepository(self::TEMPLATES_DIR, new TemplateLoader());

        $filler   = $factory->create($repo->load('open-tabs'));
        $instance = $filler->fill([]);

        $page   = new PageConfig();
        $stream = (new LayoutService(new Flattener(), $page))->layout($instance);
        $html   = (new HtmlRenderer($page))->render($stream);

        $this->assertSnapshotMatches($html, self::SNAPSHOT);
    }
}
