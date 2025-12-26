<?php

namespace ReportWriter\Report\Renderer;

class HtmlTableRenderer implements RendererInterface
{
    private string $output = '';

    public function renderBand(string $type, ?int $level, $context): string
    {
        $html = '';

        switch ($type) {
            case 'reportHeader':
                $html .= "<h1>Report Title</h1>\n";
                $html .= "<table border='1' cellpadding='5' cellspacing='0'>\n";
                $html .= "  <thead><tr><th>ID</th><th>Category</th><th>Amount</th></tr></thead>\n";
                $html .= "  <tbody>\n";
                break;

            case 'groupHeader':
                // Close previous tbody if needed, start new section
                $html .= "  </tbody>\n"; // close previous group
                $category = $context['firstRecord']['category'] ?? 'Unknown';
                $html .= "  <tr><td colspan='3'><strong>Category: $category</strong></td></tr>\n";
                $html .= "  <tbody>\n";
                break;

            case 'detail':
                $record = $context; // detail band gets the raw record
                $id = $record['id'] ?? '';
                $cat = $record['category'] ?? '';
                $amt = $record['amount'] ?? '';
                $html .= "    <tr><td>$id</td><td>$cat</td><td>$amt</td></tr>\n";
                break;

            case 'groupFooter':
                $category = $context['firstRecord']['category'] ?? 'Unknown';
                $sum = $context['sumAmount'] ?? 0;
                $count = $context['recordCount'] ?? 0;
                $html .= "    <tr><td colspan='2'><strong>Total for $category ($count items)</strong></td><td><strong>$sum</strong></td></tr>\n";
                break;

            case 'summary':
                // Could show grand totals here if you add global aggregates later
                $html .= "    <tr><td colspan='3'><em>Report complete</em></td></tr>\n";
                break;

            case 'reportFooter':
                $html .= "  </tbody>\n";
                $html .= "</table>\n";
                break;
        }

        $this->output .= $html;

        // Note: We can return '' if we went to collect only in getOutput()
        return $html;
    }

    public function getOutput(): string
    {
        return $this->output;
    }
}