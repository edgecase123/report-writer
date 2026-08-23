<?php

declare(strict_types=1);

namespace ReportWriter\App;

use PDO;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Http\HealthController;
use ReportWriter\App\Http\JsonErrorHandler;
use ReportWriter\App\Http\ReportController;
use ReportWriter\App\Reports\DailySalesFiller;
use ReportWriter\App\Reports\DataSource\SqliteDailySalesProvider;
use ReportWriter\App\Reports\ParamSpec;
use ReportWriter\App\Reports\ReportDefinition;
use ReportWriter\App\Reports\ReportRegistry;
use RuntimeException;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;
use Slim\Psr7\Factory\ResponseFactory;

final class Kernel
{
    public static function buildApp(?Container $container = null): App
    {
        $container = $container ?? self::defaultContainer();

        $app = SlimAppFactory::create();

        $app->get('/health', function ($request, $response) use ($container) {
            return $container->get(HealthController::class)->show($request, $response);
        });

        $app->get('/api/reports/{id}', function ($request, $response, array $args) use ($container) {
            return $container->get(ReportController::class)->show($request, $response, $args);
        });

        $debug = (bool) (getenv('APP_DEBUG') ?: false);
        $errorMiddleware = $app->addErrorMiddleware($debug, true, true);
        $errorMiddleware->setDefaultErrorHandler(new JsonErrorHandler(new ResponseFactory(), $debug));

        return $app;
    }

    public static function defaultContainer(): Container
    {
        $c = new Container();

        $c->set(HealthController::class, static fn () => new HealthController());

        $c->set(PDO::class, static function (): PDO {
            $path = getenv('SQLITE_PATH') ?: null;
            if ($path === null || $path === '') {
                throw new RuntimeException('SQLITE_PATH env var must be set for production use.');
            }
            return SqliteConnectionFactory::createFromPath($path);
        });

        $c->set(SqliteDailySalesProvider::class,
            static fn (Container $c) => new SqliteDailySalesProvider($c->get(PDO::class)));

        $c->set(DailySalesFiller::class,
            static fn (Container $c) => new DailySalesFiller($c->get(SqliteDailySalesProvider::class)));

        $c->set(ReportRegistry::class, static fn () => new ReportRegistry([
            new ReportDefinition(
                'daily-sales',
                'Daily Sales',
                DailySalesFiller::class,
                [new ParamSpec('date', 'date', true)]
            ),
        ]));

        $c->set(ReportController::class,
            static fn (Container $c) => new ReportController($c, $c->get(ReportRegistry::class)));

        return $c;
    }
}
