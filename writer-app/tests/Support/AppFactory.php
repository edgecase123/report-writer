<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Support;

use ReportWriter\App\Config;
use ReportWriter\App\Container;
use ReportWriter\App\Kernel;
use Slim\App;

final class AppFactory
{
    public static function buildTestApp(?callable $overrides = null, ?Config $config = null): App
    {
        $config    = $config ?? new Config(null, false);
        $container = Kernel::defaultContainer($config);
        if ($overrides !== null) {
            $overrides($container);
        }
        return Kernel::buildApp($container, $config);
    }
}
