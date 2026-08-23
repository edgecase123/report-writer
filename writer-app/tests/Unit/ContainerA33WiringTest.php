<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Config;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Kernel;
use ReportWriter\App\Reports\DataSource\SqliteOpenTabsProvider;
use ReportWriter\App\Reports\ReportRegistry;
use ReportWriter\Fill\DefinitionFiller;
use ReportWriter\Registry\DataSourceRegistry;

final class ContainerA33WiringTest extends TestCase
{
    private function container(): \ReportWriter\App\Container
    {
        $c = Kernel::defaultContainer(new Config(':memory:', false));
        $c->set(PDO::class, static fn () => SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../database/schema.sql'
        ));
        return $c;
    }

    public function testDataSourceRegistryContainsOpenTabsSource(): void
    {
        $registry = $this->container()->get(DataSourceRegistry::class);
        $this->assertTrue($registry->has('open-tabs'));
        $this->assertInstanceOf(SqliteOpenTabsProvider::class, $registry->get('open-tabs'));
    }

    public function testDataSourceRegistryStillContainsSalesByCategorySource(): void
    {
        $registry = $this->container()->get(DataSourceRegistry::class);
        $this->assertTrue($registry->has('sales-by-category'));
    }

    public function testOpenTabsFillerServiceReturnsADefinitionFiller(): void
    {
        $filler = $this->container()->get('open-tabs.filler');
        $this->assertInstanceOf(DefinitionFiller::class, $filler);
    }

    public function testReportRegistryExposesOpenTabsDefinition(): void
    {
        $registry = $this->container()->get(ReportRegistry::class);
        $def = $registry->get('open-tabs');
        $this->assertSame('open-tabs', $def->getId());
        $this->assertSame('Open Tabs', $def->getLabel());
        $this->assertSame('open-tabs.filler', $def->getFillerServiceId());
        $this->assertSame([], $def->getParams(), 'A3.3 is param-less');
    }

    public function testReportRegistryStillExposesPredecessorReports(): void
    {
        $registry = $this->container()->get(ReportRegistry::class);
        $this->assertSame('daily-sales',       $registry->get('daily-sales')->getId());
        $this->assertSame('sales-by-category', $registry->get('sales-by-category')->getId());
    }
}
