# A2 — Slim Host Skeleton + One Report End-to-End Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up a new `writer-app/` (Slim 4 host) that installs the `edgecase123/report-writer` library via a composer path repo and serves **one** report — Daily Sales, backed by SQLite — end-to-end. `docker compose up` produces a running container on `:8090` where `GET /health` returns JSON and `GET /api/reports/daily-sales?date=YYYY-MM-DD` returns rendered HTML. Two Slim smoke tests land (`BootSmokeTest`, `ReportRenderSmokeTest`).

**Architecture:** Test-drive the app from the inside out: composer skeleton → Slim front controller + `/health` (proves boot) → PSR-11 container → SQLite provider → filler → registry → controller (proves rendering) → error middleware → demo seed → Docker wrapper. Each task ends with a passing test suite and a commit. Every non-Docker task runs against host-native PHP + Composer; Docker is added last as a wrapper.

**Tech Stack:** PHP 7.4+, Composer 2, Slim 4 (`slim/slim ^4.14` + `slim/psr7 ^1.6`), PDO-SQLite, PHPUnit 9.5, Docker Compose (`report-writer-php` container on `:8090`, Apache + PHP 7.4-apache base image). No frontend work — the viewer app is A4.

**Sub-project ticket:** [012 — Implement standalone runtime (Sub-project A)](../../tickets/012-implement-standalone-runtime-subproject-a.md).

**Related decisions:**
- [ADR-001](../../09-conventions/decisions/001-slim-4-http-layer.md) — Slim 4 as the HTTP layer
- [ADR-002](../../09-conventions/decisions/002-sqlite-coffee-shop-toy-domain.md) — SQLite + coffee-shop domain
- [ADR-003](../../09-conventions/decisions/003-docker-compose-ports-and-containers.md) — Compose, port `:8090`, `report-writer-*` container names
- [ADR-011](../../09-conventions/decisions/011-docs-after-implementation.md) — no doc pages for code that doesn't exist yet
- [ADR-013](../../09-conventions/decisions/013-framework-agnostic-library.md) — Slim/SQLite/Docker are dev/test/demo scaffolding, not consumer prescription

**Prerequisites:**
- PHP 7.4+ with `pdo_sqlite` on the host (`php -m | grep -i pdo_sqlite` must print `pdo_sqlite`)
- Composer 2 on the host (`composer --version` must print 2.x)
- Docker Desktop or equivalent for Task 10+ only (the earlier tasks are host-native)
- **A1 landed:** `writer/` composer package name is `edgecase123/report-writer` and namespace root is `ReportWriter\`. Confirm by running `grep '"name"' /Users/leejenkins/dev/report-writer/writer/composer.json` — expected `"name": "edgecase123/report-writer"`.

**Working directory conventions:**
- Library-side commands run from `/Users/leejenkins/dev/report-writer/writer/`.
- App-side commands run from `/Users/leejenkins/dev/report-writer/writer-app/` (created in Task 1).
- Docker-related commands (Task 10 onward) run from `/Users/leejenkins/dev/report-writer/`.
- Git operations run from `/Users/leejenkins/dev/report-writer/` (single repo).

---

## File Structure

Everything created lives inside `writer-app/` or at the repo root. The library (`writer/`) is not modified — A2 must not touch a line under `writer/src/` or `writer/tests/`.

```
writer-app/
├── composer.json                              # NEW — writer-app dev package, requires ../writer via path repo
├── phpunit.xml                                # NEW — test runner config, bootstrap = vendor/autoload.php
├── public/
│   ├── index.php                              # NEW — Slim front controller (~15 lines)
│   └── .htaccess                              # NEW — Apache rewrite → index.php
├── src/
│   ├── Kernel.php                             # NEW — buildApp(Container): Slim\App; wires routes + middleware
│   ├── Container.php                          # NEW — hand-rolled PSR-11 container (~60 lines)
│   ├── Http/
│   │   ├── HealthController.php               # NEW — GET /health → {"status":"ok"}
│   │   ├── ReportController.php               # NEW — GET /api/reports/{id}
│   │   └── JsonErrorHandler.php               # NEW — Slim error handler → structured JSON
│   ├── Reports/
│   │   ├── ReportRegistry.php                 # NEW — lookup by id
│   │   ├── ReportDefinition.php               # NEW — value object (id, label, fillerClass, params[])
│   │   ├── ParamSpec.php                      # NEW — value object (name, type, required)
│   │   ├── DailySalesFiller.php               # NEW — implements ReportFillerInterface; uses ReportBuilder
│   │   └── DataSource/
│   │       └── SqliteDailySalesProvider.php   # NEW — implements ReportDataSourceInterface
│   └── Database/
│       └── SqliteConnectionFactory.php        # NEW — makes PDO from a path or ':memory:'
├── database/
│   ├── schema.sql                             # NEW — CREATE TABLE for categories, items, orders, order_items
│   └── seed.php                               # NEW — deterministic ~90-day demo seed (mt_srand(1))
├── bin/
│   └── console                                # NEW — CLI dispatcher; supports `db:seed`
├── data/
│   └── .gitignore                             # NEW — "*\n!.gitignore" (persist SQLite files locally, don't commit)
├── docker/
│   └── php/
│       ├── Dockerfile                         # NEW — php:7.4-apache + pdo_sqlite + Composer + vhost
│       └── vhost.conf                         # NEW — Apache vhost, DocumentRoot /app/writer-app/public
├── .env.example                               # NEW — APP_ENV, APP_DEBUG, SQLITE_PATH
└── tests/
    ├── bootstrap.php                          # NEW — require vendor/autoload.php
    ├── Support/
    │   ├── AppFactory.php                     # NEW — buildTestApp(): loads schema into :memory:, wires Kernel
    │   └── DailySalesFixture.php              # NEW — inserts a deterministic mini-set of test rows
    ├── Unit/
    │   ├── Database/
    │   │   └── SqliteConnectionFactoryTest.php    # NEW
    │   ├── Reports/
    │   │   ├── ReportRegistryTest.php             # NEW
    │   │   ├── DataSource/
    │   │   │   └── SqliteDailySalesProviderTest.php # NEW
    │   │   └── DailySalesFillerTest.php           # NEW
    │   └── Http/
    │       └── JsonErrorHandlerTest.php           # NEW
    └── Smoke/
        ├── BootSmokeTest.php                  # NEW — GET /health round-trip through Slim in-process
        └── ReportRenderSmokeTest.php          # NEW — GET /api/reports/daily-sales round-trip

# At repo root (NEW files):
docker-compose.yml
```

**Files A2 does NOT create (deferred to later sub-projects):**
- `writer-app/src/Reports/DataSource/Describable*` (Builder introspection — A5)
- `writer-app/src/Http/PreviewController.php`, `DraftController.php` (Builder — A5)
- Any additional filler beyond `DailySalesFiller` (A3)
- `writer-app/tests/Snapshot/*`, `assertReportSnapshot()`, `coffee-shop-mini.sql` (A6)
- Any `docs/` pages describing writer-app runtime (per [ADR-011](../../09-conventions/decisions/011-docs-after-implementation.md), doc pages follow after A7)
- Any frontend or Vite change (A4)
- Any `.github/workflows/*.yml` (deferred — Sub-project A ships no CI)

---

## Task 0: Verify baseline

**Files:** No source changes. Confirms A1 landed cleanly and the library test suite is green before adding a second package to the repo.

- [ ] **Step 0.1: Confirm library composer name**

Run:

```bash
grep '"name"' /Users/leejenkins/dev/report-writer/writer/composer.json
```

Expected: `"name": "edgecase123/report-writer",`. If it prints anything else, stop — A1 has not landed. Do not proceed.

- [ ] **Step 0.2: Run the library test suite**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer && composer install && vendor/bin/phpunit
```

Expected: green output ending with `OK (N tests, M assertions)` where N is roughly 100–130. Any red output = stop and investigate before starting Task 1; the baseline is unstable.

- [ ] **Step 0.3: Confirm PHP has pdo_sqlite**

Run:

```bash
php -m | grep -i pdo_sqlite
```

Expected: `pdo_sqlite` (one line). If absent, install the extension before continuing — Task 4 onward depends on it.

---

## Task 1: Create `writer-app/` composer skeleton

**Files:**
- Create: `writer-app/composer.json`
- Create: `writer-app/phpunit.xml`
- Create: `writer-app/tests/bootstrap.php`
- Create: `writer-app/data/.gitignore`

**Why:** Establishes the writer-app composer package before any source code exists. The path repository (`../writer`) is the seam that lets Slim controllers use `use ReportWriter\Builder\ReportBuilder;` starting Task 5 without publishing to Packagist.

- [ ] **Step 1.1: Create the writer-app directory**

Run:

```bash
mkdir -p /Users/leejenkins/dev/report-writer/writer-app/{public,src,database,bin,data,docker/php,tests/{Support,Unit,Smoke}}
```

- [ ] **Step 1.2: Write `writer-app/composer.json`**

Create `/Users/leejenkins/dev/report-writer/writer-app/composer.json`:

```json
{
    "name": "edgecase123/report-writer-app",
    "description": "Slim 4 demo host for edgecase123/report-writer — dev/test/demo scaffolding per ADR-013.",
    "type": "project",
    "license": "proprietary",
    "require": {
        "php": ">=7.4",
        "ext-pdo": "*",
        "ext-pdo_sqlite": "*",
        "edgecase123/report-writer": "@dev",
        "slim/slim": "^4.14",
        "slim/psr7": "^1.6"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.5"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../writer",
            "options": {
                "symlink": true
            }
        }
    ],
    "autoload": {
        "psr-4": {
            "ReportWriter\\App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "ReportWriter\\App\\Tests\\": "tests/"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

- [ ] **Step 1.3: Write `writer-app/phpunit.xml`**

Create `/Users/leejenkins/dev/report-writer/writer-app/phpunit.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheResultFile=".phpunit.result.cache"
         executionOrder="depends,defects"
         beStrictAboutOutputDuringTests="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="smoke">
            <directory>tests/Smoke</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="test" force="true"/>
        <env name="APP_DEBUG" value="1" force="true"/>
    </php>
</phpunit>
```

- [ ] **Step 1.4: Write `writer-app/tests/bootstrap.php`**

Create `/Users/leejenkins/dev/report-writer/writer-app/tests/bootstrap.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
```

- [ ] **Step 1.5: Write `writer-app/data/.gitignore`**

Create `/Users/leejenkins/dev/report-writer/writer-app/data/.gitignore`:

```
*
!.gitignore
```

This keeps the empty `data/` directory in git while ensuring `report-writer.sqlite` and other runtime artifacts stay out.

- [ ] **Step 1.6: Install composer dependencies**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && composer install
```

Expected: composer downloads `slim/slim`, `slim/psr7`, `phpunit/phpunit`, and symlinks `../writer` into `vendor/edgecase123/report-writer`. Terminal prints `Generating autoload files` and no red output.

- [ ] **Step 1.7: Sanity-check autoload wiring**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && \
php -r "require 'vendor/autoload.php'; echo class_exists(ReportWriter\\Builder\\ReportBuilder::class) ? 'OK' : 'FAIL';"
```

Expected: `OK`. If `FAIL`, the path repository is not wired — inspect `composer.json` and re-run `composer update`.

- [ ] **Step 1.8: Verify empty PHPUnit run is green**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit
```

Expected: `No tests executed!` printed as a warning; exit code 0. This proves the runner is wired even though nothing has been written.

- [ ] **Step 1.9: Commit**

```bash
cd /Users/leejenkins/dev/report-writer && \
git add writer-app/composer.json \
        writer-app/composer.lock \
        writer-app/phpunit.xml \
        writer-app/tests/bootstrap.php \
        writer-app/data/.gitignore && \
git commit -m "chore(writer-app): scaffold composer package + phpunit config

Empty Slim 4 demo package installed via path repository pointing at ../writer.
PSR-4 autoload maps ReportWriter\\App\\ to src/ and ReportWriter\\App\\Tests\\
to tests/. Sub-project A2 (Slim host skeleton) starts here."
```

---

## Task 2: Slim boot + `/health` endpoint + first smoke test

**Files:**
- Create: `writer-app/src/Kernel.php`
- Create: `writer-app/src/Http/HealthController.php`
- Create: `writer-app/tests/Support/AppFactory.php`
- Create: `writer-app/tests/Smoke/BootSmokeTest.php`
- Create: `writer-app/public/index.php`
- Create: `writer-app/public/.htaccess`

**Why:** Slim boot is the smallest possible working slice — proves the composer skeleton + Slim + PSR-7 + PHPUnit are wired correctly. `/health` gives Docker's healthcheck (ADR-003) a real endpoint to hit later. `AppFactory` is the smoke-test helper both this test and every subsequent smoke test consumes. No container, no database yet — that lands in Tasks 3–4.

- [ ] **Step 2.1: Write the failing smoke test**

Create `/Users/leejenkins/dev/report-writer/writer-app/tests/Smoke/BootSmokeTest.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Smoke;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Tests\Support\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class BootSmokeTest extends TestCase
{
    public function testHealthEndpointReturnsOkJson(): void
    {
        $app = AppFactory::buildTestApp();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');

        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $body = (string) $response->getBody();
        $this->assertSame(['status' => 'ok'], json_decode($body, true));
    }
}
```

- [ ] **Step 2.2: Run the test to confirm it fails**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Smoke/BootSmokeTest.php
```

Expected: FAIL with `Class "ReportWriter\App\Tests\Support\AppFactory" not found` (or a similar unfound-class error).

- [ ] **Step 2.3: Create `AppFactory`**

Create `/Users/leejenkins/dev/report-writer/writer-app/tests/Support/AppFactory.php`:

```php
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
```

- [ ] **Step 2.4: Create `Kernel`**

Create `/Users/leejenkins/dev/report-writer/writer-app/src/Kernel.php`:

```php
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
```

- [ ] **Step 2.5: Create `HealthController`**

Create `/Users/leejenkins/dev/report-writer/writer-app/src/Http/HealthController.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(json_encode(['status' => 'ok']));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

- [ ] **Step 2.6: Run the smoke test to verify it passes**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Smoke/BootSmokeTest.php
```

Expected: `OK (1 test, 3 assertions)`.

- [ ] **Step 2.7: Create the front controller and .htaccess**

Create `/Users/leejenkins/dev/report-writer/writer-app/public/index.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

ReportWriter\App\Kernel::buildApp()->run();
```

Create `/Users/leejenkins/dev/report-writer/writer-app/public/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [QSA,L]
</IfModule>
```

- [ ] **Step 2.8: Sanity-check via PHP's built-in server**

Run in one terminal:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && php -S 127.0.0.1:8091 -t public
```

In another terminal:

```bash
curl -s -i http://127.0.0.1:8091/health
```

Expected: `HTTP/1.1 200 OK`, `Content-Type: application/json`, body `{"status":"ok"}`. Stop the PHP server with Ctrl-C after confirming.

- [ ] **Step 2.9: Commit**

```bash
cd /Users/leejenkins/dev/report-writer && \
git add writer-app/src/Kernel.php \
        writer-app/src/Http/HealthController.php \
        writer-app/tests/Support/AppFactory.php \
        writer-app/tests/Smoke/BootSmokeTest.php \
        writer-app/public/index.php \
        writer-app/public/.htaccess && \
git commit -m "feat(writer-app): Slim boot + /health endpoint + BootSmokeTest

Smallest possible working slice: front controller, static Kernel factory,
one route (/health) returning shaped JSON, and one smoke test that drives
the app in-process via PSR-7. AppFactory helper is reused by every future
smoke test."
```

---

## Task 3: Hand-rolled PSR-11 container

**Files:**
- Create: `writer-app/src/Container.php`
- Modify: `writer-app/src/Kernel.php` — build & wire container; route callables resolve controllers via container
- Modify: `writer-app/tests/Support/AppFactory.php` — allow test-specific overrides (used by later tasks)

**Why:** [ADR-001](../../09-conventions/decisions/001-slim-4-http-layer.md) mandates a hand-rolled ~60-line PSR-11 container instead of an autowiring library. This task extracts the container BEFORE any controller needs constructor-injected dependencies — so Tasks 4–7 land those dependencies through it cleanly. Extending the AppFactory to accept overrides gives smoke tests a seam for swapping in in-memory SQLite (Task 4).

- [ ] **Step 3.1: Write the failing container test**

Create `/Users/leejenkins/dev/report-writer/writer-app/tests/Unit/ContainerTest.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use ReportWriter\App\Container;

final class ContainerTest extends TestCase
{
    public function testResolvesRegisteredFactoryOnce(): void
    {
        $container = new Container();
        $calls     = 0;
        $container->set('svc', function () use (&$calls) {
            $calls++;
            return new \stdClass();
        });

        $a = $container->get('svc');
        $b = $container->get('svc');

        $this->assertSame($a, $b, 'container must cache resolved services');
        $this->assertSame(1, $calls, 'factory must be invoked only once');
    }

    public function testHasReturnsFalseForUnknownId(): void
    {
        $container = new Container();
        $this->assertFalse($container->has('nope'));
    }

    public function testGetThrowsPsr11NotFoundForUnknownId(): void
    {
        $container = new Container();

        $this->expectException(NotFoundExceptionInterface::class);
        $container->get('nope');
    }

    public function testFactoryReceivesContainerForDependencyLookup(): void
    {
        $container = new Container();
        $container->set('dep', static fn () => 'dep-value');
        $container->set('svc', static fn (Container $c) => $c->get('dep'));

        $this->assertSame('dep-value', $container->get('svc'));
    }
}
```

- [ ] **Step 3.2: Run the test to verify it fails**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Unit/ContainerTest.php
```

Expected: FAIL with `Class "ReportWriter\App\Container" not found`.

- [ ] **Step 3.3: Create `Container`**

Create `/Users/leejenkins/dev/report-writer/writer-app/src/Container.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class Container implements ContainerInterface
{
    /** @var array<string, callable(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $resolved = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->resolved[$id]);
    }

    /**
     * @return mixed
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new class ("Service '$id' is not registered.") extends \RuntimeException implements NotFoundExceptionInterface {};
        }
        return $this->resolved[$id] = ($this->factories[$id])($this);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
```

- [ ] **Step 3.4: Run the container test to verify it passes**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Unit/ContainerTest.php
```

Expected: `OK (4 tests, 6 assertions)`.

- [ ] **Step 3.5: Register `HealthController` in the container; route callables resolve through it**

Replace `/Users/leejenkins/dev/report-writer/writer-app/src/Kernel.php` entirely with:

```php
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
```

- [ ] **Step 3.6: Allow test overrides in `AppFactory`**

Replace `/Users/leejenkins/dev/report-writer/writer-app/tests/Support/AppFactory.php` entirely with:

```php
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
```

- [ ] **Step 3.7: Confirm the BootSmokeTest still passes**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit
```

Expected: `OK (5 tests, 9 assertions)` (4 container + 1 boot smoke).

- [ ] **Step 3.8: Commit**

```bash
cd /Users/leejenkins/dev/report-writer && \
git add writer-app/src/Container.php \
        writer-app/src/Kernel.php \
        writer-app/tests/Support/AppFactory.php \
        writer-app/tests/Unit/ContainerTest.php && \
git commit -m "feat(writer-app): hand-rolled PSR-11 container per ADR-001

Container.php implements Psr\\Container\\ContainerInterface in ~30 lines with
factory registration + single-instance caching. Kernel::buildApp accepts an
optional pre-populated container so tests can override individual bindings.
AppFactory grows an \$overrides callable seam for the same reason."
```

---

## Task 4: SQLite schema + connection factory + `SqliteDailySalesProvider`

**Files:**
- Create: `writer-app/database/schema.sql`
- Create: `writer-app/src/Database/SqliteConnectionFactory.php`
- Create: `writer-app/src/Reports/DataSource/SqliteDailySalesProvider.php`
- Create: `writer-app/tests/Support/DailySalesFixture.php`
- Create: `writer-app/tests/Unit/Database/SqliteConnectionFactoryTest.php`
- Create: `writer-app/tests/Unit/Reports/DataSource/SqliteDailySalesProviderTest.php`

**Why:** The Daily Sales report needs 4 tables (`categories`, `items`, `orders`, `order_items`) — the minimum subset of the coffee-shop schema (ADR-002) required to answer "one row per closed order on date X, plus its aggregate total". Extracting `SqliteConnectionFactory` isolates the "make me a PDO" concern so tests and production wire different DSNs the same way. `DailySalesFixture` is a hand-rolled test-only inserter — the full deterministic demo seed lands in Task 9.

- [ ] **Step 4.1: Write the schema**

Create `/Users/leejenkins/dev/report-writer/writer-app/database/schema.sql`:

```sql
-- Coffee-shop POS schema (subset used by A2).
-- Full schema (staff, payments, template_drafts) lands with A3 and A5.

CREATE TABLE IF NOT EXISTS categories (
    id   INTEGER PRIMARY KEY,
    name TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS items (
    id               INTEGER PRIMARY KEY,
    category_id      INTEGER NOT NULL REFERENCES categories(id),
    name             TEXT    NOT NULL,
    unit_price_cents INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS orders (
    id         INTEGER PRIMARY KEY,
    opened_at  TEXT    NOT NULL,             -- ISO-8601 UTC
    closed_at  TEXT                          -- NULL = tab still open
);

CREATE TABLE IF NOT EXISTS order_items (
    id               INTEGER PRIMARY KEY,
    order_id         INTEGER NOT NULL REFERENCES orders(id),
    item_id          INTEGER NOT NULL REFERENCES items(id),
    quantity         INTEGER NOT NULL,
    unit_price_cents INTEGER NOT NULL         -- snapshotted at order time
);

CREATE INDEX IF NOT EXISTS orders_closed_at_idx     ON orders(closed_at);
CREATE INDEX IF NOT EXISTS order_items_order_id_idx ON order_items(order_id);
```

- [ ] **Step 4.2: Write the failing connection-factory test**

Create `/Users/leejenkins/dev/report-writer/writer-app/tests/Unit/Database/SqliteConnectionFactoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;

final class SqliteConnectionFactoryTest extends TestCase
{
    public function testCreatesInMemoryPdoWithSchemaLoaded(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../../database/schema.sql'
        );

        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
                       ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(['categories', 'items', 'order_items', 'orders'], $tables);
    }

    public function testCreatesFileBackedPdo(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rw-a2-') . '.sqlite';
        try {
            $pdo = SqliteConnectionFactory::createFromPath($tmp);
            $this->assertInstanceOf(PDO::class, $pdo);
            $this->assertFileExists($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
```

- [ ] **Step 4.3: Run the test to verify it fails**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Unit/Database/SqliteConnectionFactoryTest.php
```

Expected: FAIL with `Class "ReportWriter\App\Database\SqliteConnectionFactory" not found`.

- [ ] **Step 4.4: Create `SqliteConnectionFactory`**

Create `/Users/leejenkins/dev/report-writer/writer-app/src/Database/SqliteConnectionFactory.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Database;

use PDO;
use RuntimeException;

final class SqliteConnectionFactory
{
    public static function createFromPath(string $path): PDO
    {
        return self::configure(new PDO('sqlite:' . $path));
    }

    public static function createInMemoryWithSchema(string $schemaFile): PDO
    {
        $pdo = self::configure(new PDO('sqlite::memory:'));
        self::loadSchema($pdo, $schemaFile);
        return $pdo;
    }

    public static function loadSchema(PDO $pdo, string $schemaFile): void
    {
        if (!is_readable($schemaFile)) {
            throw new RuntimeException("Schema file not readable: {$schemaFile}");
        }
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
    }

    private static function configure(PDO $pdo): PDO
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }
}
```

- [ ] **Step 4.5: Confirm the factory test passes**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Unit/Database/SqliteConnectionFactoryTest.php
```

Expected: `OK (2 tests, 4 assertions)`.

- [ ] **Step 4.6: Create the test fixture helper**

Create `/Users/leejenkins/dev/report-writer/writer-app/tests/Support/DailySalesFixture.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Support;

use PDO;

/**
 * Inserts a deterministic mini-dataset used by A2's unit + smoke tests.
 *
 * On the date 2026-08-22 UTC: 3 closed orders totalling 2900 cents.
 * On the date 2026-08-21 UTC: 1 closed order (must be excluded by the report).
 * One unclosed order (must be excluded).
 *
 * This is NOT the demo seed (Task 9). It is a targeted fixture for tests.
 * A6 will replace it with a shared `coffee-shop-mini.sql` snapshot fixture.
 */
final class DailySalesFixture
{
    public static function load(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO categories (id, name) VALUES (1, 'Coffee'), (2, 'Pastry')");
        $pdo->exec("INSERT INTO items (id, category_id, name, unit_price_cents) VALUES
            (1, 1, 'Espresso', 500),
            (2, 1, 'Latte',    600),
            (3, 2, 'Croissant', 400)");

        // Orders on the target date (2026-08-22).
        $pdo->exec("INSERT INTO orders (id, opened_at, closed_at) VALUES
            (1001, '2026-08-22T09:10:00Z', '2026-08-22T09:15:00Z'),
            (1002, '2026-08-22T10:20:00Z', '2026-08-22T10:22:00Z'),
            (1003, '2026-08-22T14:00:00Z', '2026-08-22T14:05:00Z')");
        $pdo->exec("INSERT INTO order_items (id, order_id, item_id, quantity, unit_price_cents) VALUES
            (1, 1001, 1, 1, 500),          -- 500
            (2, 1001, 3, 1, 400),          -- 400  → order 1001 = 900
            (3, 1002, 2, 2, 600),          -- 1200 → order 1002 = 1200
            (4, 1003, 1, 1, 500),          -- 500
            (5, 1003, 3, 1, 300)           -- 300  → order 1003 = 800
        ");

        // Prior-day order (should not appear).
        $pdo->exec("INSERT INTO orders (id, opened_at, closed_at) VALUES
            (999, '2026-08-21T09:00:00Z', '2026-08-21T09:10:00Z')");
        $pdo->exec("INSERT INTO order_items (id, order_id, item_id, quantity, unit_price_cents) VALUES
            (99, 999, 2, 1, 600)");

        // Still-open order (should not appear regardless of date).
        $pdo->exec("INSERT INTO orders (id, opened_at, closed_at) VALUES
            (2000, '2026-08-22T15:00:00Z', NULL)");
        $pdo->exec("INSERT INTO order_items (id, order_id, item_id, quantity, unit_price_cents) VALUES
            (200, 2000, 1, 1, 500)");
    }
}
```

- [ ] **Step 4.7: Write the failing provider test**

Create `/Users/leejenkins/dev/report-writer/writer-app/tests/Unit/Reports/DataSource/SqliteDailySalesProviderTest.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports\DataSource;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Reports\DataSource\SqliteDailySalesProvider;
use ReportWriter\App\Tests\Support\DailySalesFixture;

final class SqliteDailySalesProviderTest extends TestCase
{
    public function testReturnsRowsForRequestedDateExcludingOpenAndOtherDates(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../../database/schema.sql'
        );
        DailySalesFixture::load($pdo);

        $provider = new SqliteDailySalesProvider($pdo);
        $rows     = $provider->fetchRows(['date' => '2026-08-22']);

        $this->assertSame(
            [
                ['order_id' => 1001, 'closed_at' => '09:15', 'total_cents' => 900],
                ['order_id' => 1002, 'closed_at' => '10:22', 'total_cents' => 1200],
                ['order_id' => 1003, 'closed_at' => '14:05', 'total_cents' => 800],
            ],
            $rows
        );
    }

    public function testReturnsEmptyArrayForDateWithNoClosedOrders(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../../database/schema.sql'
        );
        DailySalesFixture::load($pdo);

        $provider = new SqliteDailySalesProvider($pdo);
        $this->assertSame([], $provider->fetchRows(['date' => '2020-01-01']));
    }
}
```

- [ ] **Step 4.8: Run the test to verify it fails**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Unit/Reports/DataSource/SqliteDailySalesProviderTest.php
```

Expected: FAIL with `Class "ReportWriter\App\Reports\DataSource\SqliteDailySalesProvider" not found`.

- [ ] **Step 4.9: Create `SqliteDailySalesProvider`**

Create `/Users/leejenkins/dev/report-writer/writer-app/src/Reports/DataSource/SqliteDailySalesProvider.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports\DataSource;

use InvalidArgumentException;
use PDO;
use ReportWriter\Interfaces\ReportDataSourceInterface;

final class SqliteDailySalesProvider implements ReportDataSourceInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param  array{date?: string} $params
     * @return array<int, array{order_id: int, closed_at: string, total_cents: int}>
     */
    public function fetchRows(array $params): array
    {
        $date = $params['date'] ?? null;
        if (!is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new InvalidArgumentException("Parameter 'date' must be YYYY-MM-DD; got " . var_export($date, true));
        }

        $sql = <<<SQL
            SELECT
                o.id                                          AS order_id,
                strftime('%H:%M', o.closed_at)                AS closed_at,
                SUM(oi.quantity * oi.unit_price_cents)        AS total_cents
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            WHERE date(o.closed_at) = :date
              AND o.closed_at IS NOT NULL
            GROUP BY o.id
            ORDER BY o.closed_at ASC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['date' => $date]);

        return array_map(
            static fn (array $r): array => [
                'order_id'    => (int) $r['order_id'],
                'closed_at'   => (string) $r['closed_at'],
                'total_cents' => (int) $r['total_cents'],
            ],
            $stmt->fetchAll()
        );
    }
}
```

- [ ] **Step 4.10: Confirm the provider test passes**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit
```

Expected: green — `OK (9 tests, N assertions)` (1 boot + 4 container + 2 factory + 2 provider).

- [ ] **Step 4.11: Commit**

```bash
cd /Users/leejenkins/dev/report-writer && \
git add writer-app/database/schema.sql \
        writer-app/src/Database/SqliteConnectionFactory.php \
        writer-app/src/Reports/DataSource/SqliteDailySalesProvider.php \
        writer-app/tests/Support/DailySalesFixture.php \
        writer-app/tests/Unit/Database/SqliteConnectionFactoryTest.php \
        writer-app/tests/Unit/Reports/DataSource/SqliteDailySalesProviderTest.php && \
git commit -m "feat(writer-app): SQLite schema + connection factory + DailySalesProvider

Adds the minimum coffee-shop schema subset needed for the Daily Sales report
(categories, items, orders, order_items). SqliteConnectionFactory produces
PDOs (file or :memory:) with schema pre-loaded for tests. Provider filters to
closed orders on the requested date; excludes open tabs and other dates.
DailySalesFixture is a hand-rolled test-only inserter; A6 will replace with a
shared coffee-shop-mini.sql fixture."
```

---

## Task 5: `DailySalesFiller` (uses `ReportBuilder`)

**Files:**
- Create: `writer-app/src/Reports/DailySalesFiller.php`
- Create: `writer-app/tests/Unit/Reports/DailySalesFillerTest.php`

**Why:** The filler is the seam between the app's SQLite provider and the library's `ReportBuilder`. It implements `ReportFillerInterface::fill(array $params): ReportInstance` — the only library-facing contract A2 needs to satisfy. Isolating this in one small class keeps the library's fill invariant honest (`ReportFillerInterface` is the seam per `CLAUDE.md`) and gives A3's additional reports a copy-paste template.

- [ ] **Step 5.1: Write the failing filler test**

Create `/Users/leejenkins/dev/report-writer/writer-app/tests/Unit/Reports/DailySalesFillerTest.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Reports\DailySalesFiller;
use ReportWriter\App\Reports\DataSource\SqliteDailySalesProvider;
use ReportWriter\App\Tests\Support\DailySalesFixture;
use ReportWriter\Instance\ReportInstance;

final class DailySalesFillerTest extends TestCase
{
    public function testFillProducesReportInstanceWithExpectedBands(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../database/schema.sql'
        );
        DailySalesFixture::load($pdo);

        $filler   = new DailySalesFiller(new SqliteDailySalesProvider($pdo));
        $instance = $filler->fill(['date' => '2026-08-22']);

        $this->assertInstanceOf(ReportInstance::class, $instance);
        $this->assertSame('daily-sales', $instance->getReportInstanceId());

        // Non-empty band list; at least one band per row plus title.
        $bands = $instance->getBandInstances();
        $this->assertNotEmpty($bands, 'filler must produce at least the title band');
        $this->assertGreaterThanOrEqual(4, count($bands),
            'expected at least title + header + 3 detail bands');
    }

    public function testFillProducesFewerBandsWhenNoOrdersForDate(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../database/schema.sql'
        );
        DailySalesFixture::load($pdo);

        $filler        = new DailySalesFiller(new SqliteDailySalesProvider($pdo));
        $instanceEmpty = $filler->fill(['date' => '2020-01-01']);
        $instanceFull  = $filler->fill(['date' => '2026-08-22']);

        $this->assertLessThan(
            count($instanceFull->getBandInstances()),
            count($instanceEmpty->getBandInstances()),
            'empty-date report must have fewer bands than populated-date report'
        );
    }
}
```

- [ ] **Step 5.2: Run the test to verify it fails**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Unit/Reports/DailySalesFillerTest.php
```

Expected: FAIL with `Class "ReportWriter\App\Reports\DailySalesFiller" not found`.

- [ ] **Step 5.3: Create `DailySalesFiller`**

Create `/Users/leejenkins/dev/report-writer/writer-app/src/Reports/DailySalesFiller.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports;

use ReportWriter\Builder\Column;
use ReportWriter\Builder\ReportBuilder;
use ReportWriter\Instance\ReportInstance;
use ReportWriter\Interfaces\ReportDataSourceInterface;
use ReportWriter\Interfaces\ReportFillerInterface;
use ReportWriter\Registry\FormatterRegistry;

final class DailySalesFiller implements ReportFillerInterface
{
    private ReportDataSourceInterface $provider;

    public function __construct(ReportDataSourceInterface $provider)
    {
        $this->provider = $provider;
    }

    public function fill(array $params): ReportInstance
    {
        $date     = $params['date'] ?? '';
        $rows     = $this->provider->fetchRows($params);
        $currency = FormatterRegistry::defaults()->get('currencyCents');

        return ReportBuilder::create('daily-sales')
            ->title("Daily Sales — {$date}")
            ->columns([
                Column::make('order_id',    'Order',       0,   120),
                Column::make('closed_at',   'Closed',      130, 120),
                Column::make('total_cents', 'Total',       260, 120)
                    ->sum()
                    ->alignRight()
                    ->format($currency),
            ])
            ->rows($rows)
            ->build();
    }
}
```

- [ ] **Step 5.4: Confirm the filler test passes**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit
```

Expected: green. The assertions are intentionally structural ("has at least N bands", "populated has more bands than empty") rather than exact counts — the library composes bands from title/header/rows/summary in ways this test doesn't need to pin down. Task 7's smoke test will exercise the exact rendered output.

- [ ] **Step 5.5: Commit**

```bash
cd /Users/leejenkins/dev/report-writer && \
git add writer-app/src/Reports/DailySalesFiller.php \
        writer-app/tests/Unit/Reports/DailySalesFillerTest.php && \
git commit -m "feat(writer-app): DailySalesFiller implements ReportFillerInterface

Uses ReportBuilder to compose title + [order_id, closed_at, total_cents]
columns with a sum aggregate on the total. Currency formatter (cents-based)
comes from the library's default FormatterRegistry. Copy-paste template for
A3's remaining five fillers."
```

---

## Task 6: `ReportRegistry` + `ReportDefinition` + `ParamSpec`

**Files:**
- Create: `writer-app/src/Reports/ReportDefinition.php`
- Create: `writer-app/src/Reports/ParamSpec.php`
- Create: `writer-app/src/Reports/ReportRegistry.php`
- Create: `writer-app/tests/Unit/Reports/ReportRegistryTest.php`

**Why:** The controller needs a lookup: given a URL segment like `daily-sales`, return the filler service id and the accepted params (for validation + Builder introspection in A5). Three tiny value objects + a registry keep this contract explicit without pulling in a container-scanning autowire library.

- [ ] **Step 6.1: Write the failing registry test**

Create `/Users/leejenkins/dev/report-writer/writer-app/tests/Unit/Reports/ReportRegistryTest.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports;

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Reports\ParamSpec;
use ReportWriter\App\Reports\ReportDefinition;
use ReportWriter\App\Reports\ReportRegistry;

final class ReportRegistryTest extends TestCase
{
    public function testGetReturnsRegisteredDefinition(): void
    {
        $def = new ReportDefinition(
            'daily-sales',
            'Daily Sales',
            'DailySalesFillerServiceId',
            [new ParamSpec('date', 'date', true)]
        );
        $registry = new ReportRegistry([$def]);

        $this->assertSame($def, $registry->get('daily-sales'));
    }

    public function testGetThrowsWhenIdUnknown(): void
    {
        $registry = new ReportRegistry([]);
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage("Unknown report 'nope'");
        $registry->get('nope');
    }

    public function testAllReturnsRegisteredDefinitionsInInsertionOrder(): void
    {
        $a = new ReportDefinition('a', 'A', 'sid-a', []);
        $b = new ReportDefinition('b', 'B', 'sid-b', []);
        $registry = new ReportRegistry([$a, $b]);

        $this->assertSame([$a, $b], $registry->all());
    }

    public function testParamSpecExposesFields(): void
    {
        $p = new ParamSpec('date', 'date', true);
        $this->assertSame('date', $p->getName());
        $this->assertSame('date', $p->getType());
        $this->assertTrue($p->isRequired());
    }
}
```

- [ ] **Step 6.2: Run the test to verify it fails**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Unit/Reports/ReportRegistryTest.php
```

Expected: FAIL with `Class "ReportWriter\App\Reports\ReportDefinition" not found`.

- [ ] **Step 6.3: Create `ParamSpec`**

Create `/Users/leejenkins/dev/report-writer/writer-app/src/Reports/ParamSpec.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports;

final class ParamSpec
{
    private string $name;
    private string $type;    // 'date' | 'string' | 'int' | 'bool' — expanded as more reports need it
    private bool $required;

    public function __construct(string $name, string $type, bool $required)
    {
        $this->name     = $name;
        $this->type     = $type;
        $this->required = $required;
    }

    public function getName(): string    { return $this->name; }
    public function getType(): string    { return $this->type; }
    public function isRequired(): bool   { return $this->required; }
}
```

- [ ] **Step 6.4: Create `ReportDefinition`**

Create `/Users/leejenkins/dev/report-writer/writer-app/src/Reports/ReportDefinition.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports;

final class ReportDefinition
{
    private string $id;
    private string $label;
    private string $fillerServiceId;

    /** @var ParamSpec[] */
    private array $params;

    /**
     * @param ParamSpec[] $params
     */
    public function __construct(string $id, string $label, string $fillerServiceId, array $params)
    {
        $this->id              = $id;
        $this->label           = $label;
        $this->fillerServiceId = $fillerServiceId;
        $this->params          = $params;
    }

    public function getId(): string              { return $this->id; }
    public function getLabel(): string           { return $this->label; }
    public function getFillerServiceId(): string { return $this->fillerServiceId; }

    /** @return ParamSpec[] */
    public function getParams(): array           { return $this->params; }
}
```

- [ ] **Step 6.5: Create `ReportRegistry`**

Create `/Users/leejenkins/dev/report-writer/writer-app/src/Reports/ReportRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports;

use OutOfBoundsException;

final class ReportRegistry
{
    /** @var array<string, ReportDefinition> */
    private array $byId = [];

    /** @var ReportDefinition[] */
    private array $ordered = [];

    /**
     * @param ReportDefinition[] $definitions
     */
    public function __construct(array $definitions)
    {
        foreach ($definitions as $def) {
            $this->byId[$def->getId()] = $def;
            $this->ordered[]           = $def;
        }
    }

    public function get(string $id): ReportDefinition
    {
        if (!isset($this->byId[$id])) {
            throw new OutOfBoundsException("Unknown report '{$id}'");
        }
        return $this->byId[$id];
    }

    /** @return ReportDefinition[] */
    public function all(): array
    {
        return $this->ordered;
    }
}
```

- [ ] **Step 6.6: Confirm the registry test passes**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Unit/Reports/ReportRegistryTest.php
```

Expected: `OK (4 tests, 6 assertions)`.

- [ ] **Step 6.7: Commit**

```bash
cd /Users/leejenkins/dev/report-writer && \
git add writer-app/src/Reports/ParamSpec.php \
        writer-app/src/Reports/ReportDefinition.php \
        writer-app/src/Reports/ReportRegistry.php \
        writer-app/tests/Unit/Reports/ReportRegistryTest.php && \
git commit -m "feat(writer-app): ReportRegistry + ReportDefinition + ParamSpec

Immutable value objects + a keyed registry. Registry.get(id) throws
OutOfBoundsException for unknown ids (converted to a 404 by the Slim error
middleware in Task 8). A3 adds five more ReportDefinition entries; A5 uses
the ParamSpec list to describe reports to the Builder UI."
```

---

## Task 7: `ReportController` + wire the `/api/reports/{id}` route

**Files:**
- Create: `writer-app/src/Http/ReportController.php`
- Modify: `writer-app/src/Kernel.php` — register report bindings, register the route
- Create: `writer-app/tests/Smoke/ReportRenderSmokeTest.php`

**Why:** Second smoke test — proves the full path from HTTP request → registry lookup → filler → LayoutService → HtmlRenderer → HTML response. This is A2's "prove Slim wiring works" milestone. The controller stays thin (~25 lines) per ADR-001.

- [ ] **Step 7.1: Write the failing smoke test**

Create `/Users/leejenkins/dev/report-writer/writer-app/tests/Smoke/ReportRenderSmokeTest.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Smoke;

use PDO;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Container;
use ReportWriter\App\Database\SqliteConnectionFactory;
use ReportWriter\App\Tests\Support\AppFactory;
use ReportWriter\App\Tests\Support\DailySalesFixture;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ReportRenderSmokeTest extends TestCase
{
    public function testDailySalesRendersHtmlForRequestedDate(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../database/schema.sql'
        );
        DailySalesFixture::load($pdo);

        $app = AppFactory::buildTestApp(static function (Container $c) use ($pdo): void {
            $c->set(PDO::class, static fn () => $pdo);
        });

        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/api/reports/daily-sales?date=2026-08-22');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));

        $html = (string) $response->getBody();
        $this->assertStringContainsString('Daily Sales', $html);
        $this->assertStringContainsString('1001', $html, 'order id 1001 should appear in the rendered HTML');
        $this->assertStringContainsString('1002', $html, 'order id 1002 should appear in the rendered HTML');
        $this->assertStringContainsString('1003', $html, 'order id 1003 should appear in the rendered HTML');
    }
}
```

- [ ] **Step 7.2: Run the smoke test to confirm it fails**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Smoke/ReportRenderSmokeTest.php
```

Expected: FAIL — either a 404 (route not registered), or a container `NotFoundException`, or `ReportController` unfound.

- [ ] **Step 7.3: Create `ReportController`**

Create `/Users/leejenkins/dev/report-writer/writer-app/src/Http/ReportController.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Http;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReportWriter\App\Reports\ReportRegistry;
use ReportWriter\Interfaces\ReportFillerInterface;
use ReportWriter\Layout\Flattener;
use ReportWriter\Layout\LayoutService;
use ReportWriter\Layout\PageConfig;
use ReportWriter\Renderer\HtmlRenderer;

final class ReportController
{
    private ContainerInterface $container;
    private ReportRegistry $registry;

    public function __construct(ContainerInterface $container, ReportRegistry $registry)
    {
        $this->container = $container;
        $this->registry  = $registry;
    }

    /**
     * @param array<string, string> $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $definition = $this->registry->get($args['id']);

        /** @var ReportFillerInterface $filler */
        $filler   = $this->container->get($definition->getFillerServiceId());
        $params   = $request->getQueryParams();
        $instance = $filler->fill($params);

        $pageConfig = new PageConfig();
        $stream     = (new LayoutService(new Flattener(), $pageConfig))->layout($instance);
        $html       = (new HtmlRenderer($pageConfig))->render($stream);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
```

- [ ] **Step 7.4: Extend `Kernel` to wire reports + the route**

Replace `/Users/leejenkins/dev/report-writer/writer-app/src/Kernel.php` entirely with:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App;

use PDO;
use ReportWriter\App\Http\HealthController;
use ReportWriter\App\Http\ReportController;
use ReportWriter\App\Reports\DailySalesFiller;
use ReportWriter\App\Reports\DataSource\SqliteDailySalesProvider;
use ReportWriter\App\Reports\ParamSpec;
use ReportWriter\App\Reports\ReportDefinition;
use ReportWriter\App\Reports\ReportRegistry;
use RuntimeException;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

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
            return \ReportWriter\App\Database\SqliteConnectionFactory::createFromPath($path);
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
```

- [ ] **Step 7.5: Run the smoke test to verify it passes**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Smoke/ReportRenderSmokeTest.php
```

Expected: `OK (1 test, 5 assertions)`. If the HTML doesn't contain the order IDs, inspect the rendered body — the `HtmlRenderer` should include each detail band's cell text.

- [ ] **Step 7.6: Run the whole suite**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit
```

Expected: green. Total suite is 1 boot smoke + 4 container + 2 factory + 2 provider + 2 filler + 4 registry + 1 render smoke = 16 test methods. PHPUnit reports test count by method (data providers can add extra counts). What matters: zero failures, zero risky, zero warnings.

- [ ] **Step 7.7: Sanity-check via the built-in server**

Terminal 1:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && \
SQLITE_PATH=/tmp/rw-a2-manual.sqlite php -S 127.0.0.1:8091 -t public
```

Terminal 2 — seed a manual DB, then request the report:

```bash
sqlite3 /tmp/rw-a2-manual.sqlite < /Users/leejenkins/dev/report-writer/writer-app/database/schema.sql
sqlite3 /tmp/rw-a2-manual.sqlite <<'SQL'
INSERT INTO categories VALUES (1, 'Coffee');
INSERT INTO items VALUES (1, 1, 'Espresso', 500);
INSERT INTO orders (id, opened_at, closed_at) VALUES (1, '2026-08-22T09:00:00Z', '2026-08-22T09:05:00Z');
INSERT INTO order_items (id, order_id, item_id, quantity, unit_price_cents) VALUES (1, 1, 1, 1, 500);
SQL
curl -s http://127.0.0.1:8091/api/reports/daily-sales?date=2026-08-22 | head -c 800
```

Expected: HTML containing `Daily Sales — 2026-08-22`, the string `1` (order id), and a rendered table. Stop the PHP server with Ctrl-C, delete the temp DB: `rm /tmp/rw-a2-manual.sqlite`.

- [ ] **Step 7.8: Commit**

```bash
cd /Users/leejenkins/dev/report-writer && \
git add writer-app/src/Http/ReportController.php \
        writer-app/src/Kernel.php \
        writer-app/tests/Smoke/ReportRenderSmokeTest.php && \
git commit -m "feat(writer-app): ReportController + /api/reports/{id} route

Wires the full path from HTTP → registry → filler → LayoutService →
HtmlRenderer → response. ReportRenderSmokeTest exercises the whole stack
in-process against an in-memory SQLite. This is A2's 'prove Slim wiring
works' milestone.

The Kernel container wires PDO from SQLITE_PATH env var for production;
tests override it via the AppFactory \$overrides hook."
```

---

## Task 8: Structured JSON error middleware

**Files:**
- Create: `writer-app/src/Http/JsonErrorHandler.php`
- Modify: `writer-app/src/Kernel.php` — attach error middleware
- Create: `writer-app/tests/Unit/Http/JsonErrorHandlerTest.php`
- Modify: `writer-app/tests/Smoke/ReportRenderSmokeTest.php` — add "unknown report → 404 JSON" case

**Why:** Slim's default HTML error page leaks stack traces and doesn't shape well for API consumers. Section 3 of the design specifies structured JSON errors: 400 for `InvalidArgumentException`, 404 for `OutOfBoundsException` (unknown report), 500 for anything else. Debug flag adds trace fields.

- [ ] **Step 8.1: Write the failing unit test**

Create `/Users/leejenkins/dev/report-writer/writer-app/tests/Unit/Http/JsonErrorHandlerTest.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Http;

use InvalidArgumentException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Http\JsonErrorHandler;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class JsonErrorHandlerTest extends TestCase
{
    public function testMapsOutOfBoundsTo404(): void
    {
        $handler  = new JsonErrorHandler(new ResponseFactory(), false);
        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/x');
        $response = $handler($request, new OutOfBoundsException("Unknown report 'x'"), false, false, false);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(['error' => ['status' => 404, 'message' => "Unknown report 'x'"]], $payload);
    }

    public function testMapsInvalidArgumentTo400(): void
    {
        $handler  = new JsonErrorHandler(new ResponseFactory(), false);
        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/x');
        $response = $handler($request, new InvalidArgumentException('bad date'), false, false, false);

        $this->assertSame(400, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(['error' => ['status' => 400, 'message' => 'bad date']], $payload);
    }

    public function testMapsGenericExceptionTo500WithoutTraceWhenDebugOff(): void
    {
        $handler  = new JsonErrorHandler(new ResponseFactory(), false);
        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/x');
        $response = $handler($request, new RuntimeException('boom'), false, false, false);

        $this->assertSame(500, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(500, $payload['error']['status']);
        $this->assertSame('Internal server error', $payload['error']['message']);
        $this->assertArrayNotHasKey('trace', $payload['error']);
    }

    public function testIncludesTraceWhenDebugOn(): void
    {
        $handler  = new JsonErrorHandler(new ResponseFactory(), true);
        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/x');
        $response = $handler($request, new RuntimeException('boom'), false, false, false);

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('boom', $payload['error']['message']);
        $this->assertArrayHasKey('trace', $payload['error']);
        $this->assertIsString($payload['error']['trace']);
    }
}
```

- [ ] **Step 8.2: Run the test to verify it fails**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Unit/Http/JsonErrorHandlerTest.php
```

Expected: FAIL with `Class "ReportWriter\App\Http\JsonErrorHandler" not found`.

- [ ] **Step 8.3: Create `JsonErrorHandler`**

Create `/Users/leejenkins/dev/report-writer/writer-app/src/Http/JsonErrorHandler.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Http;

use InvalidArgumentException;
use OutOfBoundsException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpNotFoundException;
use Throwable;

final class JsonErrorHandler
{
    private ResponseFactoryInterface $responseFactory;
    private bool $debug;

    public function __construct(ResponseFactoryInterface $responseFactory, bool $debug)
    {
        $this->responseFactory = $responseFactory;
        $this->debug           = $debug;
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        [$status, $message] = $this->classify($exception);

        $error = ['status' => $status, 'message' => $message];
        if ($this->debug && $status === 500) {
            $error['message'] = $exception->getMessage();
            $error['trace']   = $exception->getTraceAsString();
        }

        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(json_encode(['error' => $error]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function classify(Throwable $e): array
    {
        if ($e instanceof HttpNotFoundException) {
            return [404, 'Not found'];
        }
        if ($e instanceof OutOfBoundsException) {
            return [404, $e->getMessage()];
        }
        if ($e instanceof InvalidArgumentException) {
            return [400, $e->getMessage()];
        }
        return [500, 'Internal server error'];
    }
}
```

- [ ] **Step 8.4: Attach the middleware in `Kernel`**

In `/Users/leejenkins/dev/report-writer/writer-app/src/Kernel.php`, add the following imports at the top (after the existing `use` block):

```php
use ReportWriter\App\Http\JsonErrorHandler;
use Slim\Psr7\Factory\ResponseFactory;
```

Then, inside `buildApp()`, immediately before `return $app;`, append:

```php
        $debug = (bool) (getenv('APP_DEBUG') ?: false);
        $errorMiddleware = $app->addErrorMiddleware($debug, true, true);
        $errorMiddleware->setDefaultErrorHandler(new JsonErrorHandler(new ResponseFactory(), $debug));
```

The final method body should be:

```php
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
```

- [ ] **Step 8.5: Add the 404-smoke case to `ReportRenderSmokeTest`**

Append this method inside the `ReportRenderSmokeTest` class in `/Users/leejenkins/dev/report-writer/writer-app/tests/Smoke/ReportRenderSmokeTest.php`:

```php
    public function testUnknownReportReturns404Json(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../database/schema.sql'
        );

        $app = AppFactory::buildTestApp(static function (Container $c) use ($pdo): void {
            $c->set(PDO::class, static fn () => $pdo);
        });

        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/api/reports/nope?date=2026-08-22');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(404, $payload['error']['status']);
        $this->assertStringContainsString("Unknown report 'nope'", $payload['error']['message']);
    }
```

- [ ] **Step 8.6: Run the whole suite**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit
```

Expected: green. All prior tests still pass, plus 4 new `JsonErrorHandler` tests and 1 new smoke case.

- [ ] **Step 8.7: Commit**

```bash
cd /Users/leejenkins/dev/report-writer && \
git add writer-app/src/Http/JsonErrorHandler.php \
        writer-app/src/Kernel.php \
        writer-app/tests/Unit/Http/JsonErrorHandlerTest.php \
        writer-app/tests/Smoke/ReportRenderSmokeTest.php && \
git commit -m "feat(writer-app): structured JSON error middleware

Maps OutOfBoundsException → 404, InvalidArgumentException → 400, everything
else → 500. Debug mode (APP_DEBUG=1) exposes the underlying message + trace
on 500s; production hides them. Smoke test proves unknown report ids return
shaped JSON instead of Slim's default HTML error page."
```

---

## Task 9: Deterministic demo seed + `bin/console`

**Files:**
- Create: `writer-app/database/seed.php`
- Create: `writer-app/bin/console`
- Create: `writer-app/tests/Unit/Database/SeedDeterminismTest.php`

**Why:** [ADR-002](../../09-conventions/decisions/002-sqlite-coffee-shop-toy-domain.md) requires a deterministic seed with `mt_srand(1)` producing ~90 days of coffee-shop activity so screenshots and snapshot tests are stable. `bin/console db:seed` is the CLI entry point Docker calls on first-run. The determinism test seeds twice into two in-memory DBs and asserts they produce byte-identical dumps.

- [ ] **Step 9.1: Write the failing determinism test**

Create `/Users/leejenkins/dev/report-writer/writer-app/tests/Unit/Database/SeedDeterminismTest.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;

final class SeedDeterminismTest extends TestCase
{
    public function testSeedProducesByteIdenticalRowsAcrossRuns(): void
    {
        $rowsA = $this->seedAndDump();
        $rowsB = $this->seedAndDump();

        $this->assertSame($rowsA, $rowsB, 'seed must be byte-identical across runs (ADR-002 mt_srand(1))');
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function seedAndDump(): array
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../database/schema.sql'
        );

        require __DIR__ . '/../../../database/seed.php';
        \ReportWriter\App\Database\Seed::run($pdo);

        return [
            'categories'  => $pdo->query('SELECT * FROM categories  ORDER BY id')->fetchAll(),
            'items'       => $pdo->query('SELECT * FROM items       ORDER BY id')->fetchAll(),
            'orders'      => $pdo->query('SELECT * FROM orders      ORDER BY id')->fetchAll(),
            'order_items' => $pdo->query('SELECT * FROM order_items ORDER BY id')->fetchAll(),
        ];
    }
}
```

- [ ] **Step 9.2: Run the test to verify it fails**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Unit/Database/SeedDeterminismTest.php
```

Expected: FAIL — either `seed.php` not found, or `ReportWriter\App\Database\Seed` class not found.

- [ ] **Step 9.3: Write `database/seed.php`**

Create `/Users/leejenkins/dev/report-writer/writer-app/database/seed.php`:

```php
<?php

declare(strict_types=1);

namespace ReportWriter\App\Database;

use PDO;

/**
 * Deterministic coffee-shop seed for the demo.
 *
 * Contract per ADR-002: mt_srand(1); ~90 days of activity ending on a fixed
 * anchor date (2026-08-22 UTC). Reads/writes only the 4 tables A2 defines
 * (categories, items, orders, order_items). A3 extends this to cover staff,
 * payments, and the remaining reports' data needs.
 */
final class Seed
{
    private const ANCHOR_DATE       = '2026-08-22';
    private const DAYS_OF_HISTORY   = 90;
    private const ORDERS_PER_DAY_MIN = 8;
    private const ORDERS_PER_DAY_MAX = 24;

    public static function run(PDO $pdo): void
    {
        mt_srand(1);

        $pdo->beginTransaction();
        try {
            self::wipe($pdo);
            self::insertCategories($pdo);
            self::insertItems($pdo);
            self::insertOrdersAndItems($pdo);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function wipe(PDO $pdo): void
    {
        $pdo->exec('DELETE FROM order_items');
        $pdo->exec('DELETE FROM orders');
        $pdo->exec('DELETE FROM items');
        $pdo->exec('DELETE FROM categories');
    }

    private static function insertCategories(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT INTO categories (id, name) VALUES (:id, :name)');
        foreach ([[1, 'Coffee'], [2, 'Tea'], [3, 'Pastry'], [4, 'Sandwich']] as [$id, $name]) {
            $stmt->execute(['id' => $id, 'name' => $name]);
        }
    }

    private static function insertItems(PDO $pdo): void
    {
        $catalogue = [
            // [id, category_id, name, price_cents]
            [1, 1, 'Espresso',      350],
            [2, 1, 'Americano',     400],
            [3, 1, 'Latte',         500],
            [4, 1, 'Cappuccino',    500],
            [5, 1, 'Cold Brew',     550],
            [6, 2, 'Black Tea',     350],
            [7, 2, 'Green Tea',     350],
            [8, 2, 'Chai Latte',    475],
            [9, 3, 'Croissant',     400],
            [10, 3, 'Muffin',       350],
            [11, 3, 'Scone',        375],
            [12, 4, 'Turkey Club',  1050],
            [13, 4, 'Veggie Wrap',  950],
            [14, 4, 'Grilled Ham',  1100],
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO items (id, category_id, name, unit_price_cents) VALUES (:id, :cat, :name, :price)'
        );
        foreach ($catalogue as [$id, $cat, $name, $price]) {
            $stmt->execute(['id' => $id, 'cat' => $cat, 'name' => $name, 'price' => $price]);
        }
    }

    private static function insertOrdersAndItems(PDO $pdo): void
    {
        $orderStmt = $pdo->prepare(
            'INSERT INTO orders (id, opened_at, closed_at) VALUES (:id, :opened, :closed)'
        );
        $lineStmt = $pdo->prepare(
            'INSERT INTO order_items (order_id, item_id, quantity, unit_price_cents) VALUES (:oid, :iid, :qty, :price)'
        );

        // Pre-computed catalogue for random picks.
        $itemIds    = range(1, 14);
        $itemPrices = self::pricesById($pdo);

        $orderId = 1;
        for ($dayOffset = self::DAYS_OF_HISTORY; $dayOffset >= 0; $dayOffset--) {
            $day        = date('Y-m-d', strtotime(self::ANCHOR_DATE . " -{$dayOffset} days"));
            $ordersToday = mt_rand(self::ORDERS_PER_DAY_MIN, self::ORDERS_PER_DAY_MAX);

            for ($i = 0; $i < $ordersToday; $i++) {
                $openHour   = mt_rand(6, 20);
                $openMin    = mt_rand(0, 59);
                $durMin     = mt_rand(2, 20);
                $opened     = sprintf('%sT%02d:%02d:00Z', $day, $openHour, $openMin);
                $closedTs   = strtotime($opened) + $durMin * 60;
                $closed     = gmdate('Y-m-d\TH:i:s\Z', $closedTs);

                $orderStmt->execute(['id' => $orderId, 'opened' => $opened, 'closed' => $closed]);

                $lineCount = mt_rand(1, 4);
                for ($j = 0; $j < $lineCount; $j++) {
                    $iid = $itemIds[mt_rand(0, count($itemIds) - 1)];
                    $qty = mt_rand(1, 3);
                    $lineStmt->execute([
                        'oid'   => $orderId,
                        'iid'   => $iid,
                        'qty'   => $qty,
                        'price' => $itemPrices[$iid],
                    ]);
                }
                $orderId++;
            }
        }
    }

    /**
     * @return array<int, int>
     */
    private static function pricesById(PDO $pdo): array
    {
        $out = [];
        foreach ($pdo->query('SELECT id, unit_price_cents FROM items') as $row) {
            $out[(int) $row['id']] = (int) $row['unit_price_cents'];
        }
        return $out;
    }
}
```

- [ ] **Step 9.4: Confirm the determinism test passes**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit tests/Unit/Database/SeedDeterminismTest.php
```

Expected: `OK (1 test, 1 assertion)`.

- [ ] **Step 9.5: Create `bin/console`**

Create `/Users/leejenkins/dev/report-writer/writer-app/bin/console`:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ReportWriter\App\Database\Seed;
use ReportWriter\App\Database\SqliteConnectionFactory;

require __DIR__ . '/../database/seed.php';

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'db:seed':
        $path = getenv('SQLITE_PATH') ?: (__DIR__ . '/../data/report-writer.sqlite');

        if (file_exists($path)) {
            fwrite(STDERR, "Removing existing DB at {$path}\n");
            unlink($path);
        }

        $pdo = SqliteConnectionFactory::createFromPath($path);
        SqliteConnectionFactory::loadSchema($pdo, __DIR__ . '/../database/schema.sql');
        Seed::run($pdo);

        $counts = [];
        foreach (['categories', 'items', 'orders', 'order_items'] as $t) {
            $counts[$t] = (int) $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        }
        fwrite(STDOUT, "Seeded {$path}\n");
        foreach ($counts as $t => $n) {
            fwrite(STDOUT, sprintf("  %-12s %6d\n", $t, $n));
        }
        exit(0);

    case 'help':
    default:
        fwrite(STDERR, "Usage: bin/console <command>\n");
        fwrite(STDERR, "\nCommands:\n");
        fwrite(STDERR, "  db:seed   Reset SQLITE_PATH (or data/report-writer.sqlite) and load deterministic demo data.\n");
        exit($command === 'help' ? 0 : 1);
}
```

- [ ] **Step 9.6: Make `bin/console` executable**

Run:

```bash
chmod +x /Users/leejenkins/dev/report-writer/writer-app/bin/console
```

- [ ] **Step 9.7: Smoke-test the console**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && \
SQLITE_PATH=/tmp/rw-a2-console.sqlite bin/console db:seed && \
sqlite3 /tmp/rw-a2-console.sqlite 'SELECT COUNT(*) FROM orders' && \
rm /tmp/rw-a2-console.sqlite
```

Expected: `bin/console db:seed` prints `Seeded /tmp/rw-a2-console.sqlite` and per-table counts; the `sqlite3` query prints a number roughly between 720 and 2160 (90 days × 8–24 orders/day).

- [ ] **Step 9.8: Commit**

```bash
cd /Users/leejenkins/dev/report-writer && \
git add writer-app/database/seed.php \
        writer-app/bin/console \
        writer-app/tests/Unit/Database/SeedDeterminismTest.php && \
git commit -m "feat(writer-app): deterministic demo seed + bin/console db:seed

Seed produces ~90 days of coffee-shop activity via mt_srand(1) per ADR-002.
bin/console dispatches CLI commands; db:seed resets SQLITE_PATH (or the
default data/report-writer.sqlite), reloads schema, and runs Seed::run.
Determinism test seeds twice and asserts byte-identical row dumps."
```

---

## Task 10: Docker Compose + `report-writer-php` container

**Files:**
- Create: `writer-app/docker/php/Dockerfile`
- Create: `writer-app/docker/php/vhost.conf`
- Create: `writer-app/.env.example`
- Create: `docker-compose.yml` (repo root)

**Why:** [ADR-003](../../09-conventions/decisions/003-docker-compose-ports-and-containers.md) mandates: single container `report-writer-php` on `:8090` (avoiding the leagues stack's `:8080`), Apache + PHP 7.4 + PDO-SQLite + Composer, healthcheck on `/health`, bind-mount source for hot reload, dedicated bind-mount for `writer-app/data/` so the SQLite file survives container restarts. A2 does NOT include the `report-writer-vite` service — that arrives with A4.

- [ ] **Step 10.1: Write the Dockerfile**

Create `/Users/leejenkins/dev/report-writer/writer-app/docker/php/Dockerfile`:

```dockerfile
FROM php:7.4-apache

# System deps
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libsqlite3-dev \
        sqlite3 \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions we actually need
RUN docker-php-ext-install pdo pdo_sqlite

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache rewrite + custom vhost
RUN a2enmod rewrite
COPY docker/php/vhost.conf /etc/apache2/sites-available/000-default.conf

# Application directory
WORKDIR /app

# Ensure the writer-app/data/ dir is writeable by Apache at runtime.
RUN mkdir -p /app/writer-app/data && chown -R www-data:www-data /app/writer-app/data

EXPOSE 80
```

- [ ] **Step 10.2: Write the Apache vhost**

Create `/Users/leejenkins/dev/report-writer/writer-app/docker/php/vhost.conf`:

```apache
<VirtualHost *:80>
    DocumentRoot /app/writer-app/public

    <Directory /app/writer-app/public>
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

- [ ] **Step 10.3: Write `.env.example`**

Create `/Users/leejenkins/dev/report-writer/writer-app/.env.example`:

```
# Copy to .env for local overrides. .env is gitignored (see repo root .gitignore).
APP_ENV=dev
APP_DEBUG=1
SQLITE_PATH=/app/writer-app/data/report-writer.sqlite
```

- [ ] **Step 10.4: Add `.env` to `.gitignore`**

Run:

```bash
cd /Users/leejenkins/dev/report-writer && \
if ! grep -qxF 'writer-app/.env' .gitignore 2>/dev/null; then echo 'writer-app/.env' >> .gitignore; fi
```

- [ ] **Step 10.5: Write `docker-compose.yml`**

Create `/Users/leejenkins/dev/report-writer/docker-compose.yml`:

```yaml
services:
  report-writer-php:
    container_name: report-writer-php
    build:
      context: ./writer-app
      dockerfile: docker/php/Dockerfile
    ports:
      - "8090:80"
    volumes:
      - ./:/app
      - ./writer-app/data:/app/writer-app/data
    environment:
      APP_ENV: ${APP_ENV:-dev}
      APP_DEBUG: ${APP_DEBUG:-1}
      SQLITE_PATH: ${SQLITE_PATH:-/app/writer-app/data/report-writer.sqlite}
    healthcheck:
      test: ["CMD", "curl", "-fsS", "http://localhost/health"]
      interval: 10s
      timeout: 3s
      retries: 5
      start_period: 20s
```

- [ ] **Step 10.6: Add curl to the container image so the healthcheck can run**

Edit the Dockerfile at `/Users/leejenkins/dev/report-writer/writer-app/docker/php/Dockerfile`, add `curl` to the apt-get install list:

```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libsqlite3-dev \
        sqlite3 \
        curl \
    && rm -rf /var/lib/apt/lists/*
```

- [ ] **Step 10.7: Confirm the plan does not commit the compose stack yet**

No commit yet — Task 11 boots the stack end-to-end and, if that passes, commits everything together (Dockerfile + vhost + compose + .env.example + .gitignore change) atomically.

---

## Task 11: End-to-end verification + atomic commit

**Files:** No new source files. Verification-driven; commits Task 10's Docker artifacts once real docker-compose confirms they work.

**Why:** The plan's "prove Slim wiring works" milestone is only real when `docker compose up` produces a working demo end-to-end. This task boots the container, seeds it, curls the endpoints, and — if everything is green — commits Task 10's Docker files atomically.

- [ ] **Step 11.1: Build the container**

Run:

```bash
cd /Users/leejenkins/dev/report-writer && docker compose build report-writer-php
```

Expected: image builds without error. First build downloads the `php:7.4-apache` and `composer:2` base images, installs the apt packages, and prints `naming to docker.io/library/report-writer-report-writer-php`.

- [ ] **Step 11.2: Boot the container**

Run:

```bash
cd /Users/leejenkins/dev/report-writer && docker compose up -d report-writer-php
```

Expected: `Container report-writer-php  Started`. Verify with:

```bash
docker ps --filter name=report-writer-php --format '{{.Names}} {{.Status}}'
```

Expected: `report-writer-php Up X seconds (healthy)` after the healthcheck settles (allow ~30 seconds).

- [ ] **Step 11.3: Install app-side composer deps inside the container**

Run:

```bash
docker exec report-writer-php composer install --working-dir=/app/writer-app
```

Expected: `Generating autoload files` and no red output. This is a one-time first-run step (the source is bind-mounted, so `vendor/` written inside the container appears on the host).

- [ ] **Step 11.4: Seed the demo database inside the container**

Run:

```bash
docker exec report-writer-php php /app/writer-app/bin/console db:seed
```

Expected: `Seeded /app/writer-app/data/report-writer.sqlite` plus per-table counts (categories 4, items 14, orders ~1400, order_items ~3500). The counts will match your local host run of `bin/console db:seed` because both use `mt_srand(1)`.

- [ ] **Step 11.5: Hit `/health` from the host**

Run:

```bash
curl -s -i http://localhost:8090/health
```

Expected: `HTTP/1.1 200 OK`, `Content-Type: application/json`, body `{"status":"ok"}`.

- [ ] **Step 11.6: Hit the Daily Sales endpoint from the host**

Run:

```bash
curl -s -i "http://localhost:8090/api/reports/daily-sales?date=2026-08-22" | head -c 1500
```

Expected: `HTTP/1.1 200 OK`, `Content-Type: text/html; charset=utf-8`, HTML body containing `Daily Sales — 2026-08-22` and multiple order rows. If the response is empty for that date, pick another date within the seeded range: `date=2026-06-01` (roughly the middle of the 90-day window).

- [ ] **Step 11.7: Confirm error middleware works end-to-end**

Run:

```bash
curl -s -i "http://localhost:8090/api/reports/nope"
```

Expected: `HTTP/1.1 404 Not Found`, `Content-Type: application/json`, body containing `"Unknown report 'nope'"`.

- [ ] **Step 11.8: Tear down the container**

Run:

```bash
cd /Users/leejenkins/dev/report-writer && docker compose down
```

Expected: `Container report-writer-php  Removed`.

- [ ] **Step 11.9: Commit the Docker artifacts atomically**

```bash
cd /Users/leejenkins/dev/report-writer && \
git add writer-app/docker/php/Dockerfile \
        writer-app/docker/php/vhost.conf \
        writer-app/.env.example \
        .gitignore \
        docker-compose.yml && \
git commit -m "feat(docker): report-writer-php container on :8090 per ADR-003

Single Slim/Apache/PHP 7.4/PDO-SQLite/Composer container. Bind-mounts repo
source for hot reload plus writer-app/data/ for SQLite persistence.
Healthcheck curls /health. Non-conflicting port :8090 avoids leagues stack's
:8080. Vite service (:5174) arrives with A4.

Verified end-to-end: docker compose up → composer install → db:seed →
curl /health (200 JSON) → curl /api/reports/daily-sales (200 HTML) →
curl /api/reports/nope (404 JSON)."
```

---

## Task 12: Sanity-check final state

**Files:** No changes. Verification only.

- [ ] **Step 12.1: Library still untouched**

Run:

```bash
cd /Users/leejenkins/dev/report-writer && \
git log --since='30 days ago' --oneline -- writer/src writer/tests | head -20
```

Expected: no commits from A2 tasks. Only A1 commits and older. If any commit touched `writer/src/**` or `writer/tests/**` during A2, investigate — A2 is not supposed to modify the library.

- [ ] **Step 12.2: No framework imports leaked into the library**

Per [ADR-013](../../09-conventions/decisions/013-framework-agnostic-library.md):

```bash
grep -rn '^use Slim\|^use Symfony\|^use Illuminate\|^use Laravel' \
    /Users/leejenkins/dev/report-writer/writer/src 2>&1
```

Expected: no output.

- [ ] **Step 12.3: writer-app doesn't accidentally leak into writer/**

Run:

```bash
grep -rn 'ReportWriter\\\\App' /Users/leejenkins/dev/report-writer/writer/src 2>&1
```

Expected: no output.

- [ ] **Step 12.4: All writer-app tests green**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer-app && vendor/bin/phpunit
```

Expected: green. Complete A2 suite: 1 boot smoke + 4 container + 2 factory + 2 provider + 2 filler + 4 registry + 1 render smoke + 4 error handler + 1 unknown-report smoke + 1 seed determinism = 22 test methods. Zero failures, zero risky, zero warnings.

- [ ] **Step 12.5: Library tests still green**

Run:

```bash
cd /Users/leejenkins/dev/report-writer/writer && vendor/bin/phpunit
```

Expected: green — the same count as the A1 baseline.

- [ ] **Step 12.6: docker-compose config validates**

Run:

```bash
cd /Users/leejenkins/dev/report-writer && docker compose config
```

Expected: prints the resolved YAML with `report-writer-php` service, port `8090:80`, and no errors.

- [ ] **Step 12.7: No `.env` file committed**

Run:

```bash
git ls-files writer-app/.env 2>&1
```

Expected: no output. The `.env.example` should be tracked; the real `.env` must not.

- [ ] **Step 12.8: Update the plans README to mark A2 complete**

Edit `/Users/leejenkins/dev/report-writer/docs/superpowers/plans/README.md`, locate the A2 row in the "Sub-project A" table, and change:

```
| A2 | Slim host skeleton + one report end-to-end | ... | A1 | *(not yet written)* | ⏭ Next |
```

to:

```
| A2 | Slim host skeleton + one report end-to-end | ... | A1 | [`2026-08-22-a2-slim-host-skeleton.md`](2026-08-22-a2-slim-host-skeleton.md) | ✅ Complete (2026-08-22) |
```

Also flip the A3 row's Status from `Pending` to `⏭ Next`.

Commit:

```bash
cd /Users/leejenkins/dev/report-writer && \
git add docs/superpowers/plans/README.md && \
git commit -m "docs(plans): mark A2 complete, promote A3 to next"
```

---

## Post-plan followups

- Update `docs/tickets/README.md`'s ledger to reflect any part of Ticket 012 A2 delivers (probably a note in the "Progress" column rather than closing 012 — the epic is still open).
- The `data/.gitignore` pattern (`*` + `!.gitignore`) is intentional. Future PRs adding demo fixtures should ship SQL files under `writer-app/database/`, not committed SQLite blobs under `data/`.
- No `docs/06-runtime/`, `docs/handoff/adoption.md`, or `docs/02-setup/quickstart.md` pages are written by A2 — per [ADR-011](../../09-conventions/decisions/011-docs-after-implementation.md), those land with A7 after the full demo works.

---

## What this plan does NOT do

Deferred to later A sub-plans or later tickets:

- Additional reports (Sales by Category, Sales by Category → Item, Open Tabs, Register Close, Full Menu Book) → A3
- `DescribableDataSource` interface + `/api/data-sources` + `/api/formatters` endpoints → A5
- `template_drafts` table + `/api/drafts` CRUD + `PreviewSmokeTest` + `DraftCrudSmokeTest` → A5
- Frontend router / HomeLanding / viewer wiring to router-derived URL / `report-writer-vite` container → A4
- BuilderPage / JsonEditor / CodeMirror / autocomplete → A5
- `coffee-shop-mini.sql` fixture + `assertReportSnapshot()` helper + 6 snapshot tests → A6
- `docs/02-setup/*`, `docs/06-runtime/*`, `docs/handoff/adoption.md` → A7
- CI workflow (`.github/workflows/*.yml`) → not in Sub-project A at all
- Any change under `writer/src/**` or `writer/tests/**` → not A2's job by design (see Task 12 Step 12.1)

