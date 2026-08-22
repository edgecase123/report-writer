<?php

declare(strict_types=1);

namespace foreup\Reporting\Template;

class ReportTemplate
{
    private string $reportDefinitionId;
    private string $dataSource;
    /** @var ParamDefinition[] keyed by name */
    private array $params;
    /** @var BandTemplate[] */
    private array $bands;

    /**
     * @param ParamDefinition[] $params
     * @param BandTemplate[]    $bands
     */
    public function __construct(
        string $reportDefinitionId,
        string $dataSource,
        array $params,
        array $bands
    ) {
        $this->reportDefinitionId = $reportDefinitionId;
        $this->dataSource         = $dataSource;
        $this->params             = $params;
        $this->bands              = $bands;
    }

    public static function fromArray(array $data): self
    {
        foreach (['report_definition_id', 'data_source'] as $required) {
            if (empty($data[$required])) {
                throw new \InvalidArgumentException("Template missing required field: '{$required}'");
            }
        }
        if (!isset($data['bands']) || !is_array($data['bands'])) {
            throw new \InvalidArgumentException("Template missing required field: 'bands'");
        }

        $params = [];
        foreach ($data['params'] ?? [] as $name => $def) {
            $params[$name] = ParamDefinition::fromArray($name, $def);
        }

        $bands = [];
        foreach ($data['bands'] as $band) {
            $bands[] = BandTemplate::fromArray($band);
        }

        return new self(
            (string) $data['report_definition_id'],
            (string) $data['data_source'],
            $params,
            $bands
        );
    }

    public function getReportDefinitionId(): string { return $this->reportDefinitionId; }
    public function getDataSource(): string         { return $this->dataSource; }

    /** @return ParamDefinition[] */
    public function getParams(): array { return $this->params; }

    /** @return BandTemplate[] */
    public function getBands(): array  { return $this->bands; }
}
