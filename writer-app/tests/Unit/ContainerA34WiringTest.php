<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Config;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Kernel;
use ReportWriter\App\Reports\DataSource\SqliteSalesByCategoryItemProvider;
use ReportWriter\App\Reports\ReportRegistry;
use ReportWriter\App\Reports\SalesByCategoryItemFiller;

final class ContainerA34WiringTest extends TestCase
{
    private function container(): \ReportWriter\App\Container
    {
        // Same pattern as A3.2/A3.3 wiring tests.
        $c = Kernel::defaultContainer(new Config(':memory:', false));
        $c->set(PDO::class, static fn () => SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../database/schema.sql'
        ));
        return $c;
    }

    public function testProviderIsResolvable(): void
    {
        $provider = $this->container()->get(SqliteSalesByCategoryItemProvider::class);
        $this->assertInstanceOf(SqliteSalesByCategoryItemProvider::class, $provider);
    }

    public function testFillerIsResolvable(): void
    {
        $filler = $this->container()->get(SalesByCategoryItemFiller::class);
        $this->assertInstanceOf(SalesByCategoryItemFiller::class, $filler);
    }

    public function testReportRegistryExposesSalesByCategoryItemDefinition(): void
    {
        $registry = $this->container()->get(ReportRegistry::class);
        $def = $registry->get('sales-by-category-item');
        $this->assertSame('sales-by-category-item',                       $def->getId());
        $this->assertSame('Sales by Category → Item',                     $def->getLabel());
        $this->assertSame(SalesByCategoryItemFiller::class,               $def->getFillerServiceId());
        $params = $def->getParams();
        $this->assertCount(1, $params);
        $this->assertSame('date', $params[0]->getName());
        $this->assertTrue($params[0]->isRequired());
    }

    public function testReportRegistryStillExposesPredecessorReports(): void
    {
        // Regression guard: A3.4 must not overwrite prior registrations.
        $registry = $this->container()->get(ReportRegistry::class);
        $this->assertSame('daily-sales',       $registry->get('daily-sales')->getId());
        $this->assertSame('sales-by-category', $registry->get('sales-by-category')->getId());
        $this->assertSame('open-tabs',         $registry->get('open-tabs')->getId());
    }
}
