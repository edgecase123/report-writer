<?php

declare(strict_types=1);

namespace foreup\Reporting\Definition;

class BandDefinition
{
    private string $bandType;

    public function __construct(string $bandType)
    {
        $this->bandType = $bandType;
    }

    public function getBandType(): string
    {
        return $this->bandType;
    }
}
