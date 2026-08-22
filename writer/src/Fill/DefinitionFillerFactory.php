<?php

declare(strict_types=1);

namespace ReportWriter\Fill;

use ReportWriter\Registry\DataSourceRegistry;
use ReportWriter\Registry\FormatterRegistry;
use ReportWriter\Template\ReportTemplate;

class DefinitionFillerFactory
{
    private DataSourceRegistry $sources;
    private FormatterRegistry $formatters;

    public function __construct(DataSourceRegistry $sources, FormatterRegistry $formatters)
    {
        $this->sources    = $sources;
        $this->formatters = $formatters;
    }

    public function create(ReportTemplate $template): DefinitionFiller
    {
        return new DefinitionFiller(
            $template,
            $this->sources,
            $this->formatters
        );
    }
}
