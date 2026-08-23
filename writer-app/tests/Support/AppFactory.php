<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Support;

use ReportWriter\App\Container;
use ReportWriter\App\Kernel;
use Slim\App;

final class AppFactory
{
    /**
     * @param callable(Container): void|null $overrides
     *   Optional mutator that runs against the default container before app boot.
     */
    public static function buildTestApp(?callable $overrides = null): App
    {
        $container = Kernel::defaultContainer();
        if ($overrides !== null) {
            $overrides($container);
        }
        return Kernel::buildApp($container);
    }
}
