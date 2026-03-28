<?php

declare(strict_types=1);

namespace ReportWriter\Fill;

use ReportWriter\Definition\ReportDefinition;

final class FillEngine
{
    public function fill(ReportDefinition $reportDefinition, array $rows): FilledReport
    {
        $bandInstances = [];
        $bandCounter = 1;
        $elementCounter = 1;

        foreach ($rows as $row) {
            foreach ($reportDefinition->getBands() as $bandDefinition) {
                if ($bandDefinition->getType() !== 'detail') {
                    continue;
                }

                $elementInstances = [];

                foreach ($bandDefinition->getElements() as $elementDefinition) {
                    $value = $row[$elementDefinition->getExpression()] ?? null;

                    $elementInstances[] = new ElementInstance(
                        'el_' . $elementCounter++,
                        $elementDefinition->getId(),
                        $elementDefinition->getKind(),
                        $elementDefinition->getX(),
                        $elementDefinition->getY(),
                        $elementDefinition->getWidth(),
                        $elementDefinition->getHeight(),
                        new ResolvedContent('text', $value === null ? '' : (string) $value)
                    );

                    $bandInstances[] = new BandInstance(
                        'band_' . $bandCounter++,
                        $bandDefinition->getId(),
                        $bandDefinition->getType(),
                        $elementInstances
                    );
                }
            }
        }

        return new FilledReport($reportDefinition->getId(), $bandInstances);
    }
}