<?php

declare(strict_types=1);

namespace foreup\Reporting\Instance\Content;

class SubreportContent extends ElementContent
{
    private string $subreportInstanceId;

    public function __construct(string $subreportInstanceId)
    {
        $this->subreportInstanceId = $subreportInstanceId;
    }

    public function getType(): string { return 'subreport'; }
    public function getSubreportInstanceId(): string { return $this->subreportInstanceId; }
    public function isSplittable(): bool { return false; }

    public function toArray(): array
    {
        return ['type' => $this->getType(), 'subreport_instance_id' => $this->subreportInstanceId];
    }
}
