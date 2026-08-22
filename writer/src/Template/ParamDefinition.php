<?php

declare(strict_types=1);

namespace ReportWriter\Template;

class ParamDefinition
{
    private string $name;
    private string $type;
    private bool $required;

    public function __construct(string $name, string $type, bool $required)
    {
        $this->name     = $name;
        $this->type     = $type;
        $this->required = $required;
    }

    public static function fromArray(string $name, array $def): self
    {
        return new self(
            $name,
            $def['type']     ?? 'string',
            $def['required'] ?? false
        );
    }

    public function getName(): string  { return $this->name; }
    public function getType(): string  { return $this->type; }
    public function isRequired(): bool { return $this->required; }
}
