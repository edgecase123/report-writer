<?php

namespace ReportWriter\Report\Renderer;

interface RendererInterface
{
    /**
     * Render a specific band.
     *
     * @param string $type    e.g. 'reportHeader', 'groupHeader', 'detail', 'groupFooter', 'summary', etc.
     * @param int|null $level For grouped bands, the group level (0-based). Null for non-grouped.
     * @param mixed $context  The context array/object passed from the report (aggregates, records, etc.)
     * @return string         The rendered output for this band
     */
    public function renderBand(string $type, ?int $level, $context): string;

    /**
     * Optional: get the full rendered report after all bands are precessed.
     * @return string
     */
    public function getOutput(): string;
}
