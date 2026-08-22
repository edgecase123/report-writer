<?php

declare(strict_types=1);

namespace ReportWriter\Template;

class TemplateLoader
{
    public function load(string $filePath): ReportTemplate
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Template file not found: {$filePath}");
        }

        $json = file_get_contents($filePath);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException("Invalid JSON in template file: {$filePath}");
        }

        return ReportTemplate::fromArray($data);
    }

    public function loadFromArray(array $data): ReportTemplate
    {
        return ReportTemplate::fromArray($data);
    }
}
