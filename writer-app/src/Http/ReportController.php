<?php

declare(strict_types=1);

namespace ReportWriter\App\Http;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReportWriter\App\Reports\ReportRegistry;
use ReportWriter\Interfaces\ReportFillerInterface;
use ReportWriter\Layout\LayoutService;
use ReportWriter\Renderer\HtmlRenderer;

final class ReportController
{
    private ContainerInterface $container;
    private ReportRegistry $registry;
    private LayoutService $layoutService;
    private HtmlRenderer $renderer;

    public function __construct(
        ContainerInterface $container,
        ReportRegistry $registry,
        LayoutService $layoutService,
        HtmlRenderer $renderer
    ) {
        $this->container     = $container;
        $this->registry      = $registry;
        $this->layoutService = $layoutService;
        $this->renderer      = $renderer;
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

        $stream = $this->layoutService->layout($instance);
        $html   = $this->renderer->render($stream);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
