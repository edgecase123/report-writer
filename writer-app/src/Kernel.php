<?php

declare(strict_types=1);

namespace ReportWriter\App;

use ReportWriter\App\Http\HealthController;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

/**
 * Boots the Slim application and wires routes.
 *
 * Kept as a pure static factory so tests (via ReportWriter\App\Tests\Support\AppFactory)
 * and the CLI/front controller build the same app the same way.
 */
final class Kernel
{
    public static function buildApp(): App
    {
        $app = SlimAppFactory::create();

        $app->get('/health', HealthController::class . ':show');

        return $app;
    }
}
