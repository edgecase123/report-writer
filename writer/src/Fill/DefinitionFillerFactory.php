<?php

declare(strict_types=1);

namespace foreup\Reporting\Fill;

use foreup\Reporting\Registry\DataSourceRegistry;
use foreup\Reporting\Registry\FormatterRegistry;
use foreup\Reporting\Template\ReportTemplate;

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
