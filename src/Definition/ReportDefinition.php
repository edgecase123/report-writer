<?php

declare(strict_types=1);

namespace ReportWriter\Definition;

final class ReportDefinition
{
    private string $id;
    private array $bands = [];

    /**
     * @param string $id
     * @param BandDefinition[]  $bands
     */
    public function __construct(
        string $id,
        array  $bands = []
    ) {
        $this->bands = $bands;
        $this->id = $id;
    }

    /**
     * @return self
     * @param string $id
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return BandDefinition[]
     */
    public function getBands(): array
    {
        return $this->bands;
    }

    public function setBands(array $bands): self
    {
        $this->bands = $bands;
        return $this;
    }
}