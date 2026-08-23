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
