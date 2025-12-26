<?php

namespace ReportWriter\Report;

use ReportWriter\Report\Renderer\RendererInterface;

class HtmlReport extends AbstractReport
{
    private string $output = '';

    public function __construct(RendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    protected function renderBand(string $type, ?int $level = null, $context = null): string
    {
        $this->renderer->renderBand($type, $level, $context ?? []);

        return '';
    }

    public function render(): string
    {
        parent::render();

        return $this->renderer->getOutput();
    }
}