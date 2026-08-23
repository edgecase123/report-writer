<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports;

final class ReportDefinition
{
    private string $id;
    private string $label;
    private string $fillerServiceId;

    /** @var ParamSpec[] */
    private array $params;

    /**
     * @param ParamSpec[] $params
     */
    public function __construct(string $id, string $label, string $fillerServiceId, array $params)
    {
        $this->id              = $id;
        $this->label           = $label;
        $this->fillerServiceId = $fillerServiceId;
        $this->params          = $params;
    }

    public function getId(): string              { return $this->id; }
    public function getLabel(): string           { return $this->label; }
    public function getFillerServiceId(): string { return $this->fillerServiceId; }

    /** @return ParamSpec[] */
    public function getParams(): array           { return $this->params; }
}
