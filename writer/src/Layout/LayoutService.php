<?php

declare(strict_types=1);

namespace ReportWriter\Layout;

use ReportWriter\Exceptions\ElementExceedsPageException;
use ReportWriter\Instance\BandInstance;
use ReportWriter\Instance\Content\TextContent;
use ReportWriter\Instance\ElementInstance;
use ReportWriter\Instance\ReportInstance;
use ReportWriter\Stream\Page;
use ReportWriter\Stream\PositionedElement;
use ReportWriter\Stream\ReportStream;

class LayoutService
{
    private Flattener $flattener;
    private PageConfig $pageConfig;

    public function __construct(Flattener $flattener, PageConfig $pageConfig)
    {
        $this->flattener  = $flattener;
        $this->pageConfig = $pageConfig;
    }

    public function layout(ReportInstance $report): ReportStream
    {
        $bands = $this->flattener->flatten($report);

        $pages       = [];
        $currentPage = new Page(1);
        $cursor      = $this->pageConfig->getMarginTop();
        $usable      = $this->pageConfig->printableHeight();
        $bottom      = $this->pageConfig->getMarginTop() + $usable;

        foreach ($bands as $band) {
            $bandHeight = $this->bandHeight($band);
            $remaining  = $bottom - $cursor;

            if ($this->fits($bandHeight, $remaining)) {
                $this->placeBand($band, $cursor, $currentPage);
                $cursor += $bandHeight + $band->getRowSpacing();
            } elseif ($this->isSplittable($band)) {
                [$cursor, $currentPage, $pages] = $this->splitAndPlace(
                    $band, $bandHeight, $cursor, $remaining, $currentPage, $pages
                );
                $cursor += $band->getRowSpacing();
            } else {
                if ($bandHeight > $usable) {
                    $el = $band->getElements()[0];
                    throw ElementExceedsPageException::forElement(
                        $el->getInstanceId(),
                        $bandHeight,
                        $usable
                    );
                }
                $pages[]     = $currentPage;
                $currentPage = new Page(count($pages) + 1);
                $cursor      = $this->pageConfig->getMarginTop();
                $this->placeBand($band, $cursor, $currentPage);
                $cursor += $bandHeight + $band->getRowSpacing();
            }
        }

        $pages[] = $currentPage;

        return new ReportStream($pages);
    }

    /**
     * Band height is the maximum element height within the band.
     * All elements in a band share the same y baseline (multi-column rows).
     */
    private function bandHeight(BandInstance $band): float
    {
        $max = 0.0;
        foreach ($band->getElements() as $el) {
            if ($el->getHeight() > $max) {
                $max = $el->getHeight();
            }
        }
        return $max;
    }

    /**
     * Resolves an element's effective width. A sentinel value of 0.0 means
     * "no declared width" — the abstract-vs-physical boundary between Fill
     * and Layout. Fill declares intent; Layout substitutes the concrete
     * printable page width. All PositionedElement instances leaving
     * LayoutService carry concrete positive widths.
     */
    private function resolvedWidth(ElementInstance $el): float
    {
        return $el->getWidth() ?: $this->pageConfig->printableWidth();
    }

    private function fits(float $height, float $remaining): bool
    {
        return $height <= $remaining;
    }

    /**
     * A band is splittable only when it contains exactly one splittable element
     * (e.g. a multi-line TextContent). Multi-column bands are never split.
     */
    private function isSplittable(BandInstance $band): bool
    {
        $elements = $band->getElements();
        return count($elements) === 1 && $elements[0]->getContent()->isSplittable();
    }

    private function placeBand(BandInstance $band, float $cursorY, Page $page): void
    {
        $marginLeft = $this->pageConfig->getMarginLeft();
        $bandType   = $band->getBandType();
        foreach ($band->getElements() as $el) {
            $page->addElement(new PositionedElement(
                $el->getInstanceId(),
                $marginLeft + $el->getX(),
                $cursorY + $el->getY(),
                $this->resolvedWidth($el),
                $el->getHeight(),
                $el->getContent(),
                $bandType,
                $el->getTextAlign()
            ));
        }
    }

    private function splitAndPlace(
        BandInstance $band,
        float $bandHeight,
        float $cursor,
        float $remaining,
        Page $currentPage,
        array $pages
    ): array {
        /** @var TextContent $content */
        $element    = $band->getElements()[0];
        $content    = $element->getContent();
        $lineHeight = $content->getLineHeight();

        $linesOnCurrentPage = (int) floor($remaining / $lineHeight);

        $marginLeft = $this->pageConfig->getMarginLeft();
        $bandType   = $band->getBandType();

        if ($linesOnCurrentPage > 0) {
            [$firstContent, $restContent] = $content->split($linesOnCurrentPage);
            $firstHeight = $linesOnCurrentPage * $lineHeight;

            $currentPage->addElement(new PositionedElement(
                $element->getInstanceId(),
                $marginLeft + $element->getX(),
                $cursor + $element->getY(),
                $this->resolvedWidth($element),
                $firstHeight,
                $firstContent,
                $bandType,
                $element->getTextAlign()
            ));
        } else {
            $restContent = $content;
        }

        $pages[]     = $currentPage;
        $currentPage = new Page(count($pages) + 1);
        $cursor      = $this->pageConfig->getMarginTop();

        $restHeight = $bandHeight - ($linesOnCurrentPage * $lineHeight);
        $currentPage->addElement(new PositionedElement(
            $element->getInstanceId(),
            $marginLeft + $element->getX(),
            $cursor + $element->getY(),
            $this->resolvedWidth($element),
            $restHeight,
            $restContent,
            $bandType,
            $element->getTextAlign()
        ));
        $cursor += $restHeight;

        return [$cursor, $currentPage, $pages];
    }
}
