<?php

declare(strict_types=1);

namespace foreup\Reporting\Template;

class BandTemplate
{
    private string $id;
    private string $type;
    private ?string $groupBy;
    private ?string $dataSource;
    private float $rowSpacing;
    /** @var ElementTemplate[] */
    private array $elements;

    /** @param ElementTemplate[] $elements */
    public function __construct(string $id, string $type, ?string $groupBy, ?string $dataSource, float $rowSpacing, array $elements)
    {
        $this->id         = $id;
        $this->type       = $type;
        $this->groupBy    = $groupBy;
        $this->dataSource = $dataSource;
        $this->rowSpacing = $rowSpacing;
        $this->elements   = $elements;
    }

    public static function fromArray(array $data): self
    {
        foreach (['id', 'type', 'elements'] as $required) {
            if (empty($data[$required])) {
                throw new \InvalidArgumentException("Band requires '{$required}'");
            }
        }
        $elements = [];
        foreach ($data['elements'] as $el) {
            $elements[] = ElementTemplate::fromArray($el);
        }
        return new self(
            (string) $data['id'],
            (string) $data['type'],
            isset($data['group_by']) ? (string) $data['group_by'] : null,
            isset($data['data_source']) ? (string) $data['data_source'] : null,
            isset($data['row_spacing']) ? (float) $data['row_spacing'] : 0.0,
            $elements
        );
    }

    public function getId(): string          { return $this->id; }
    public function getType(): string        { return $this->type; }
    public function getGroupBy(): ?string    { return $this->groupBy; }
    public function getDataSource(): ?string { return $this->dataSource; }
    public function getRowSpacing(): float   { return $this->rowSpacing; }

    /** @return ElementTemplate[] */
    public function getElements(): array     { return $this->elements; }
}
