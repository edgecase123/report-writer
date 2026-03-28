<?php

declare(strict_types=1);

namespace ReportWriter\Definition;

final class BandDefinition
{
    /**
     * @param ElementDefinition[] $elements
     */
    public function __construct(
        private readonly string $id,
        private ?string $type = null,
        private array  $elements = [],
    ) {

    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getElements(): array
    {
        return $this->elements;
    }

    public function setElements(array $elements): self
    {
        $this->elements = $elements;
        return $this;
    }

    public function addElement(ElementDefinition $element): void
    {
        $this->elements[] = $element;
    }

    public function getElement(string $id): ElementDefinition
    {
        return $this->elements[$id];
    }
}