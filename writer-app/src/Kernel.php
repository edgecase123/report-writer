<?php

declare(strict_types=1);

namespace ReportWriter\App;

use ReportWriter\App\Http\HealthController;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

/**
 * Boots the Slim application and wires routes.
 *
 * `buildApp()` accepts an optional pre-populated Container so tests can
 * override individual bindings (e.g. swap the real SQLite PDO for :memory:).
 * When no container is passed, the production defaults are wired.
 */
final class Kernel
{
    public static function buildApp(?Container $container = null): App
    {
        $container = $container ?? self::defaultContainer();

        $app = SlimAppFactory::create();

        $app->get('/health', function ($request, $response) use ($container) {
            return $container->get(HealthController::class)->show($request, $response);
        });

        return $app;
    }

    public static function defaultContainer(): Container
    {
        $c = new Container();
        $c->set(HealthController::class, static fn () => new HealthController());
        return $c;
    }
}
