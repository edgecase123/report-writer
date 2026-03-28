<?php

namespace ReportWriter\Parser;

use ReportWriter\Definition\BandDefinition;
use ReportWriter\Definition\ReportDefinition;

final class ReportDefinitionParser
{
    public static function fromJsonFile(string $path): ReportDefinition
    {
        $json = file_get_contents($path);

        if ($json === false) {
            throw new \RuntimeException("Unable to read file: {$path}");
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in: {$path}");
        }

        return self::fromArray($data);
    }

    public static function fromArray(array $data): ReportDefinition
    {
        $report = new ReportDefinition('test');
        $report->reportId = $data['report_id'];

        foreach ($data['bands'] as $bandData) {
            $band = new BandDefinition('band');
            $band->bandId = $bandData['band_id'];
            $band->bandType = $bandData['band_type'];
            $band->height = $bandData['height'];

            foreach ($bandData['elements'] as $elData) {
                $el = new ElementDefinition();
                $el->elementId = $elData['element_id'];
                $el->elementType = $elData['element_type'];
                $el->x = $elData['x'];
                $el->y = $elData['y'];
                $el->width = $elData['width'];
                $el->height = $elData['height'];
                $el->expression = $elData['expression'];

                $band->elements[] = $el;
            }

            $report->bands[] = $band;
        }

        return $report;
    }
}