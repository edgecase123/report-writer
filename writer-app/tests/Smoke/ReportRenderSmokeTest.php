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
}
