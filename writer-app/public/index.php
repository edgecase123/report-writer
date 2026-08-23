<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

ReportWriter\App\Kernel::buildApp(null, ReportWriter\App\Config::fromEnv())->run();
