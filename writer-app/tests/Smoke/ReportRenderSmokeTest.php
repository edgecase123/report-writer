<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Smoke;

use PDO;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Container;
use ReportWriter\App\Tests\Support\AppFactory;
use ReportWriter\App\Tests\Support\DailySalesFixture;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ReportRenderSmokeTest extends TestCase
{
    public function testDailySalesRendersHtmlForRequestedDate(): void
    {
        $pdo = DailySalesFixture::newPdo();

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

    public function testSalesByCategoryRespondsWithHtml(): void
    {
        $pdo = DailySalesFixture::newPdo();

        $app = AppFactory::buildTestApp(static function (Container $c) use ($pdo): void {
            $c->set(PDO::class, static fn () => $pdo);
        });

        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/api/reports/sales-by-category?date=2026-08-22');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('Sales by Category', (string) $response->getBody());
    }

    public function testOpenTabsRespondsWithHtml(): void
    {
        $pdo = DailySalesFixture::newPdo();

        $app = AppFactory::buildTestApp(static function (Container $c) use ($pdo): void {
            $c->set(PDO::class, static fn () => $pdo);
        });

        $request  = (new ServerRequestFactory())->createServerRequest('GET', '/api/reports/open-tabs');
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('Open Tabs', (string) $response->getBody());
    }

    public function testUnknownReportReturns404Json(): void
    {
        $pdo = DailySalesFixture::newPdo();

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
}
