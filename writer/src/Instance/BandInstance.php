<?php

declare(strict_types=1);

namespace ReportWriter\Instance;

class BandInstance
{
    private string $bandInstanceId;
    private string $bandType;
    /** @var ElementInstance[] */
    private array $elements;
    private ?string $parentElementInstanceId;
    private float $rowSpacing;

    /** @param ElementInstance[] $elements */
    public function __construct(
        string $bandInstanceId,
        string $bandType,
        array $elements,
        ?string $parentElementInstanceId = null,
        float $rowSpacing = 0.0
    ) {
        $this->bandInstanceId          = $bandInstanceId;
        $this->bandType                = $bandType;
        $this->elements                = $elements;
        $this->parentElementInstanceId = $parentElementInstanceId;
        $this->rowSpacing              = $rowSpacing;
    }

    public function getBandInstanceId(): string { return $this->bandInstanceId; }
    public function getBandType(): string { return $this->bandType; }

    /** @return ElementInstance[] */
    public function getElements(): array { return $this->elements; }
    public function getParentElementInstanceId(): ?string { return $this->parentElementInstanceId; }
    public function getRowSpacing(): float { return $this->rowSpacing; }
}
