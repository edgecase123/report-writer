<?php

declare(strict_types=1);

namespace ReportWriter\Definition;

final class ReportDefinition
{
    /**
     * @param string $id
     * @param BandDefinition[]  $bands
     */
    public function __construct(
        private string $id,
        private array $bands,
    ) {
    }


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
}