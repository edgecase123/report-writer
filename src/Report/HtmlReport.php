<?php

namespace ReportWriter\Report;

use ReportWriter\Report\Renderer\HtmlTableRenderer;
use ReportWriter\Report\Renderer\RendererInterface;

class HtmlReport extends AbstractReport
{
    protected ?RendererInterface $renderer;

    public function __construct(
        RendererInterface $renderer
    ) {
        $this->renderer = $renderer;

        if ($this->renderer instanceof HtmlTableRenderer) {
            $this->renderer->setReport($this);
        }
    }

    protected function renderBand(string $type, ?int $level = null, $context = null): string
    {
        $this->getRenderer()?->renderBand($type, $level, $context ?? []);

        return '';
    }

    public function render(): string
    {
        parent::render();

        return $this->getRenderer()?->getOutput() ?? '';
    }
}