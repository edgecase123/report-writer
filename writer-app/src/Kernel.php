<?php

declare(strict_types=1);

namespace ReportWriter\App;

use PDO;
use ReportWriter\App\Config;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Http\HealthController;
use ReportWriter\App\Http\JsonErrorHandler;
use ReportWriter\App\Http\ReportController;
use ReportWriter\App\Reports\DailySalesFiller;
use ReportWriter\App\Reports\DataSource\SqliteDailySalesProvider;
use ReportWriter\App\Reports\DataSource\SqliteOpenTabsProvider;
use ReportWriter\App\Reports\DataSource\SqliteSalesByCategoryProvider;
use ReportWriter\App\Reports\JsonTemplateRepository;
use ReportWriter\App\Reports\ParamSpec;
use ReportWriter\App\Reports\ReportDefinition;
use ReportWriter\App\Reports\ReportRegistry;
use ReportWriter\Fill\DefinitionFiller;
use ReportWriter\Fill\DefinitionFillerFactory;
use ReportWriter\Layout\Flattener;
use ReportWriter\Layout\LayoutService;
use ReportWriter\Layout\PageConfig;
use ReportWriter\Registry\DataSourceRegistry;
use ReportWriter\Registry\FormatterRegistry;
use ReportWriter\Renderer\HtmlRenderer;
use ReportWriter\Template\TemplateLoader;
use RuntimeException;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;
use Slim\Psr7\Factory\ResponseFactory;

final class Kernel
{
    public static function buildApp(?Container $container = null, ?Config $config = null): App
    {
        $config    = $config ?? Config::fromEnv();
        $container = $container ?? self::defaultContainer($config);

        $app = SlimAppFactory::create();

        $app->get('/health', function ($request, $response) use ($container) {
            return $container->get(HealthController::class)->show($request, $response);
        });

        $app->get('/api/reports/{id}', function ($request, $response, array $args) use ($container) {
            return $container->get(ReportController::class)->show($request, $response, $args);
        });

        $errorMiddleware = $app->addErrorMiddleware($config->appDebug(), true, true);
        $errorMiddleware->setDefaultErrorHandler(new JsonErrorHandler(new ResponseFactory(), $config->appDebug()));

        return $app;
    }

    public static function defaultContainer(Config $config): Container
    {
        $c = new Container();

        $c->set(HealthController::class, static fn () => new HealthController());

        $c->set(PDO::class, static function () use ($config): PDO {
            $path = $config->sqlitePath();
            if ($path === null) {
                throw new RuntimeException('SQLITE_PATH env var must be set for production use.');
            }
            return SqliteConnectionFactory::createFromPath($path);
        });

        $c->set(SqliteDailySalesProvider::class,
            static fn (Container $c) => new SqliteDailySalesProvider($c->get(PDO::class)));

        $c->set(DailySalesFiller::class,
            static fn (Container $c) => new DailySalesFiller($c->get(SqliteDailySalesProvider::class)));

        $c->set(SqliteSalesByCategoryProvider::class,
            static fn (Container $c) => new SqliteSalesByCategoryProvider($c->get(PDO::class)));

        $c->set(SqliteOpenTabsProvider::class,
            static fn (Container $c) => new SqliteOpenTabsProvider($c->get(PDO::class)));

        $c->set(FormatterRegistry::class,
            static fn () => FormatterRegistry::defaults());

        $c->set(DataSourceRegistry::class, static function (Container $c): DataSourceRegistry {
            $registry = new DataSourceRegistry();
            $registry->register('sales-by-category', $c->get(SqliteSalesByCategoryProvider::class));
            $registry->register('open-tabs',         $c->get(SqliteOpenTabsProvider::class));
            return $registry;
        });

        $c->set(DefinitionFillerFactory::class,
            static fn (Container $c) => new DefinitionFillerFactory(
                $c->get(DataSourceRegistry::class),
                $c->get(FormatterRegistry::class)
            ));

        $c->set(TemplateLoader::class,
            static fn () => new TemplateLoader());

        $c->set(JsonTemplateRepository::class,
            static fn (Container $c) => new JsonTemplateRepository(
                __DIR__ . '/../templates',
                $c->get(TemplateLoader::class)
            ));

        $c->set('sales-by-category.filler', static function (Container $c): DefinitionFiller {
            /** @var JsonTemplateRepository $repo */
            $repo = $c->get(JsonTemplateRepository::class);
            /** @var DefinitionFillerFactory $factory */
            $factory = $c->get(DefinitionFillerFactory::class);
            return $factory->create($repo->load('sales-by-category'));
        });

        $c->set('open-tabs.filler', static function (Container $c): DefinitionFiller {
            /** @var JsonTemplateRepository $repo */
            $repo = $c->get(JsonTemplateRepository::class);
            /** @var DefinitionFillerFactory $factory */
            $factory = $c->get(DefinitionFillerFactory::class);
            return $factory->create($repo->load('open-tabs'));
        });

        $c->set(ReportRegistry::class, static fn () => new ReportRegistry([
            new ReportDefinition(
                'daily-sales',
                'Daily Sales',
                DailySalesFiller::class,
                [new ParamSpec('date', 'date', true)]
            ),
            new ReportDefinition(
                'sales-by-category',
                'Sales by Category',
                'sales-by-category.filler',
                [new ParamSpec('date', 'date', true)]
            ),
            new ReportDefinition(
                'open-tabs',
                'Open Tabs',
                'open-tabs.filler',
                []
            ),
        ]));

        $c->set(PageConfig::class,   static fn () => new PageConfig());
        $c->set(Flattener::class,    static fn () => new Flattener());
        $c->set(LayoutService::class,
            static fn (Container $c) => new LayoutService($c->get(Flattener::class), $c->get(PageConfig::class)));
        $c->set(HtmlRenderer::class,
            static fn (Container $c) => new HtmlRenderer($c->get(PageConfig::class)));

        $c->set(ReportController::class,
            static fn (Container $c) => new ReportController(
                $c,
                $c->get(ReportRegistry::class),
                $c->get(LayoutService::class),
                $c->get(HtmlRenderer::class)
            ));

        return $c;
    }
}
