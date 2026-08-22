<?php

declare(strict_types=1);

namespace ReportWriter\Template;

class ContentDefinition
{
    private string $type;
    private ?string $value;
    private ?string $field;
    private ?string $format;
    private ?string $fn;

    public function __construct(
        string $type,
        ?string $value,
        ?string $field,
        ?string $format,
        ?string $fn
    ) {
        $this->type   = $type;
        $this->value  = $value;
        $this->field  = $field;
        $this->format = $format;
        $this->fn     = $fn;
    }

    public static function fromArray(array $data): self
    {
        if (empty($data['type'])) {
            throw new \InvalidArgumentException('Content requires type');
        }
        return new self(
            (string) $data['type'],
            isset($data['value'])  ? (string) $data['value']  : null,
            isset($data['field'])  ? (string) $data['field']  : null,
            isset($data['format']) ? (string) $data['format'] : null,
            isset($data['fn'])     ? (string) $data['fn']     : null
        );
    }

    public function getType(): string   { return $this->type; }
    public function getValue(): ?string { return $this->value; }
    public function getField(): ?string { return $this->field; }
    public function getFormat(): ?string { return $this->format; }
    public function getFn(): ?string    { return $this->fn; }
}
