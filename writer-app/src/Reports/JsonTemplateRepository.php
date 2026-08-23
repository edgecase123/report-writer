<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports;

use InvalidArgumentException;
use ReportWriter\Template\ReportTemplate;
use ReportWriter\Template\TemplateLoader;

/**
 * Loads JSON report templates from a fixed directory.
 *
 * Names are constrained to [a-z0-9_-]+ (no dots, no slashes, no absolute
 * paths) so callers cannot escape the templates directory. Delegates the
 * actual JSON parsing to the library's TemplateLoader.
 */
final class JsonTemplateRepository
{
    private string $templateDir;
    private TemplateLoader $loader;

    public function __construct(string $templateDir, TemplateLoader $loader)
    {
        $this->templateDir = rtrim($templateDir, '/');
        $this->loader      = $loader;
    }

    public function load(string $name): ReportTemplate
    {
        if (preg_match('/^[a-z0-9_-]+$/', $name) !== 1) {
            throw new InvalidArgumentException(
                "Template name must match [a-z0-9_-]+; got " . var_export($name, true)
            );
        }
        return $this->loader->load($this->templateDir . '/' . $name . '.json');
    }
}
