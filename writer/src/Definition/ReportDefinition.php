<?php

declare(strict_types=1);

namespace foreup\Reporting\Definition;

class ReportDefinition
{
    private string $reportDefinitionId;
    /** @var BandDefinition[] */
    private array $bands;
    /** @var ElementDefinition[] */
    private array $elements;

    /** @param BandDefinition[] $bands @param ElementDefinition[] $elements */
    public function __construct(string $reportDefinitionId, array $bands, array $elements)
    {
        $this->reportDefinitionId = $reportDefinitionId;
        $this->bands              = $bands;
        $this->elements           = $elements;
    }

    public function getReportDefinitionId(): string { return $this->reportDefinitionId; }

    /** @return BandDefinition[] */
    public function getBands(): array { return $this->bands; }

    /** @return ElementDefinition[] */
    public function getElements(): array { return $this->elements; }
}
