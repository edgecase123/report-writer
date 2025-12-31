<?php

namespace ReportWriter\Report;

use ReportWriter\Report\Renderer\HtmlTableRenderer;
use ReportWriter\Report\Renderer\RendererInterface;

class Report extends AbstractReport
{
    protected ?RendererInterface $renderer;

    public function getGroupBuilders(): array
    {
        return $this->groupBuilders;
    }

    public function __construct(
        ?RendererInterface $renderer = null
    ) {
        $this->renderer = $renderer;

        if ($this->renderer instanceof RendererInterface) {
            $this->renderer->setReport($this);
        }
    }

    protected function renderBand(string $type, ?int $level = null, $context = null): string
    {
        return $this->getRenderer()?->renderBand($type, $level, $context ?? []);
    }

    public function render(): string
    {
        parent::render();

        return $this->getRenderer()?->getOutput() ?? '';
    }
}