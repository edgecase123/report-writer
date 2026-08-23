<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Config;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Kernel;
use ReportWriter\App\Reports\DataSource\SqliteSalesByCategoryProvider;
use ReportWriter\App\Reports\JsonTemplateRepository;
use ReportWriter\App\Reports\ReportRegistry;
use ReportWriter\Fill\DefinitionFiller;
use ReportWriter\Fill\DefinitionFillerFactory;
use ReportWriter\Registry\DataSourceRegistry;
use ReportWriter\Registry\FormatterRegistry;

final class ContainerA32WiringTest extends TestCase
{
    private function container(): \ReportWriter\App\Container
    {
        $c = Kernel::defaultContainer(new Config(':memory:', false));
        $c->set(PDO::class, static fn () => SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../database/schema.sql'
        ));
        return $c;
    }

    public function testDataSourceRegistryIsShared(): void
    {
        $c = $this->container();
        $r1 = $c->get(DataSourceRegistry::class);
        $r2 = $c->get(DataSourceRegistry::class);
        $this->assertSame($r1, $r2, 'DataSourceRegistry must be a singleton');
        $this->assertInstanceOf(DataSourceRegistry::class, $r1);
    }

    public function testDataSourceRegistryContainsSalesByCategorySource(): void
    {
        $registry = $this->container()->get(DataSourceRegistry::class);
        $this->assertTrue($registry->has('sales-by-category'));
        $this->assertInstanceOf(SqliteSalesByCategoryProvider::class, $registry->get('sales-by-category'));
    }

    public function testFormatterRegistryIsResolvableAndProvidesCentsFormatter(): void
    {
        $formatters = $this->container()->get(FormatterRegistry::class);
        $this->assertInstanceOf(FormatterRegistry::class, $formatters);
        $format = $formatters->get('cents');
        $this->assertSame('$1.23', $format(123));
    }

    public function testDefinitionFillerFactoryIsResolvable(): void
    {
        $factory = $this->container()->get(DefinitionFillerFactory::class);
        $this->assertInstanceOf(DefinitionFillerFactory::class, $factory);
    }

    public function testJsonTemplateRepositoryPointsAtTemplatesDir(): void
    {
        $repo = $this->container()->get(JsonTemplateRepository::class);
        $this->assertInstanceOf(JsonTemplateRepository::class, $repo);
        $tmpl = $repo->load('sales-by-category');
        $this->assertSame('sales-by-category', $tmpl->getReportDefinitionId());
    }

    public function testSalesByCategoryFillerServiceReturnsADefinitionFiller(): void
    {
        $filler = $this->container()->get('sales-by-category.filler');
        $this->assertInstanceOf(DefinitionFiller::class, $filler);
    }

    public function testReportRegistryExposesSalesByCategoryDefinition(): void
    {
        $registry = $this->container()->get(ReportRegistry::class);
        $def = $registry->get('sales-by-category');
        $this->assertSame('sales-by-category', $def->getId());
        $this->assertSame('Sales by Category', $def->getLabel());
        $this->assertSame('sales-by-category.filler', $def->getFillerServiceId());
        $params = $def->getParams();
        $this->assertCount(1, $params);
        $this->assertSame('date', $params[0]->getName());
        $this->assertTrue($params[0]->isRequired());
    }
}
