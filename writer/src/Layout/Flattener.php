<?php

declare(strict_types=1);

namespace ReportWriter\Layout;

use ReportWriter\Exceptions\MissingSubreportException;
use ReportWriter\Exceptions\RecursiveSubreportException;
use ReportWriter\Instance\BandInstance;
use ReportWriter\Instance\Content\SubreportContent;
use ReportWriter\Instance\ReportInstance;

class Flattener
{
    /**
     * Flatten a ReportInstance into an ordered list of BandInstances,
     * recursively inlining subreport bands in document order.
     * Bands containing a SubreportContent element are replaced by the
     * subreport's own bands.
     *
     * @return BandInstance[]
     * @throws MissingSubreportException
     * @throws RecursiveSubreportException
     */
    public function flatten(ReportInstance $report, array $visited = []): array
    {
        $bands = [];

        foreach ($report->getBandInstances() as $band) {
            $inlined = $this->inlineBand($band, $report, $visited);
            $bands   = array_merge($bands, $inlined);
        }

        return $bands;
    }

    /**
     * Returns the band as-is if it contains no subreport elements.
     * If any element carries a SubreportContent, the band is replaced
     * by the flattened bands of the referenced subreport.
     *
     * @return BandInstance[]
     */
    private function inlineBand(BandInstance $band, ReportInstance $report, array $visited): array
    {
        foreach ($band->getElements() as $element) {
            $content = $element->getContent();

            if (!($content instanceof SubreportContent)) {
                continue;
            }

            $id = $content->getSubreportInstanceId();

            if (in_array($id, $visited, true)) {
                throw RecursiveSubreportException::forId($id);
            }

            $subreport = $report->getSubreportInstance($id);

            if ($subreport === null) {
                throw MissingSubreportException::forId($id);
            }

            $childVisited = array_merge($visited, [$id]);

            $childReport = new ReportInstance(
                $id,
                $subreport->getBandInstances(),
                $report->getSubreportInstances()
            );

            return $this->flatten($childReport, $childVisited);
        }

        return [$band];
    }
}
