<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Support;

use ReportWriter\App\Kernel;
use Slim\App;

/**
 * Builds a Slim App wired for in-process smoke testing.
 *
 * Later tasks add database/registry dependencies; this factory is the seam where
 * those are injected without any real HTTP.
 */
final class AppFactory
{
    public static function buildTestApp(): App
    {
        return Kernel::buildApp();
    }
}
