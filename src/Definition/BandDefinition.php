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
        private readonly string $type,
        private readonly array  $elements,
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

    public function getElements(): array
    {
        return $this->elements;
    }
}