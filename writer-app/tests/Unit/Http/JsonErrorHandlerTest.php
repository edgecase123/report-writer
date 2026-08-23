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
