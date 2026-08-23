<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports;

final class ParamSpec
{
    private string $name;
    private string $type;    // 'date' | 'string' | 'int' | 'bool' — expanded as more reports need it
    private bool $required;

    public function __construct(string $name, string $type, bool $required)
    {
        $this->name     = $name;
        $this->type     = $type;
        $this->required = $required;
    }

    public function getName(): string    { return $this->name; }
    public function getType(): string    { return $this->type; }
    public function isRequired(): bool   { return $this->required; }
}
