<?php

namespace ReportWriter\Report;

use ReportWriter\Report\Renderer\HtmlTableRenderer;
use ReportWriter\Report\Renderer\RendererInterface;

class HtmlReport extends AbstractReport
{
    protected ?RendererInterface $renderer;

    private AbstractReport $report;

    public function __construct(
        RendererInterface $renderer,
        ?AbstractReport $report = null
    ) {
        $this->renderer = $renderer;
        $this->report = $report ?? $this;
    }

    protected function renderBand(string $type, ?int $level = null, $context = null): string
    {
        // If renderer is HtmlTableRenderer, inject the report so it can access columns
        if ($this->renderer instanceof HtmlTableRenderer) {
            $this->renderer->setReport($this->report);
        }

        $this->renderer->renderBand($type, $level, $context ?? []);

        return '';
    }

    public function render(): string
    {
        parent::render();

        return $this->renderer->getOutput();
    }
}