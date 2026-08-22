<?php

declare(strict_types=1);

namespace foreup\Reporting\Definition;

class ElementDefinition
{
    private string $id;
    private string $type;
    private string $band;
    private ?string $subreportDefinitionId;

    public function __construct(
        string $id,
        string $type,
        string $band,
        ?string $subreportDefinitionId = null
    ) {
        $this->id                    = $id;
        $this->type                  = $type;
        $this->band                  = $band;
        $this->subreportDefinitionId = $subreportDefinitionId;
    }

    public function getId(): string { return $this->id; }
    public function getType(): string { return $this->type; }
    public function getBand(): string { return $this->band; }
    public function getSubreportDefinitionId(): ?string { return $this->subreportDefinitionId; }
    public function isSubreport(): bool { return $this->type === 'subreport'; }
}
