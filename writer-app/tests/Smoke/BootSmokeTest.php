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
