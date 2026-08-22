<?php

declare(strict_types=1);

namespace foreup\Reporting\Renderer;

use foreup\Reporting\Instance\Content\TextContent;
use foreup\Reporting\Layout\PageConfig;
use foreup\Reporting\Stream\Page;
use foreup\Reporting\Stream\PositionedElement;
use foreup\Reporting\Stream\ReportStream;

class HtmlRenderer implements RendererInterface
{
    private PageConfig $pageConfig;
    private StyleMap $styleMap;

    public function __construct(PageConfig $pageConfig, ?StyleMap $styleMap = null)
    {
        $this->pageConfig = $pageConfig;
        $this->styleMap   = $styleMap ?? StyleMap::defaults();
    }

    public function contentType(): string
    {
        return 'text/html; charset=utf-8';
    }

    public function render(ReportStream $stream): string
    {
        $html = $this->head();

        foreach ($stream->getPages() as $page) {
            $html .= $this->renderPage($page);
        }

        $html .= $this->foot();

        return $html;
    }

    private function renderPage(Page $page): string
    {
        $w = $this->pageConfig->getWidth();
        $h = $this->pageConfig->getHeight();

        $out = sprintf(
            '<div class="fu-page" style="width:%.2fpt;height:%.2fpt;" data-page="%d">',
            $w,
            $h,
            $page->getPageNumber()
        );

        foreach ($page->getElements() as $element) {
            $out .= $this->renderElement($element);
        }

        foreach ($this->bandGroups($page->getElements()) as [$bandType, $elements]) {
            if (!empty($this->styleMap->getBandStyleFor($bandType))) {
                $out .= $this->renderBandOverlay($elements, $bandType);
            }
        }

        $out .= '</div>';

        return $out;
    }

    /**
     * Groups consecutive elements that share the same bandType.
     *
     * @param  PositionedElement[] $elements
     * @return array<int, array{0: string, 1: PositionedElement[]}>
     */
    private function bandGroups(array $elements): array
    {
        $groups = [];
        foreach ($elements as $el) {
            $last = count($groups) - 1;
            if ($last < 0 || $groups[$last][0] !== $el->getBandType()) {
                $groups[] = [$el->getBandType(), [$el]];
            } else {
                $groups[$last][1][] = $el;
            }
        }
        return $groups;
    }

    /**
     * Emits a full-width overlay div for band-level styles (e.g. border-bottom on col-header).
     * Elements render at their own absolute positions; this div sits on top spanning the full width.
     *
     * @param PositionedElement[] $elements
     */
    private function renderBandOverlay(array $elements, string $bandType): string
    {
        $minY = PHP_FLOAT_MAX;
        $maxH = 0.0;
        foreach ($elements as $el) {
            if ($el->getY() < $minY) {
                $minY = $el->getY();
            }
            if ($el->getHeight() > $maxH) {
                $maxH = $el->getHeight();
            }
        }

        $style = sprintf('top:%.2fpt;height:%.2fpt;', $minY, $maxH);
        $class = 'fu-band-overlay fu-band-overlay-' . StyleMap::sanitize($bandType);

        return sprintf('<div class="%s" style="%s"></div>', $class, $style);
    }

    private function renderElement(PositionedElement $el): string
    {
        $content = $el->getContent();

        if (!($content instanceof TextContent)) {
            return '';
        }

        $style = sprintf(
            'left:%.2fpt;top:%.2fpt;width:%.2fpt;height:%.2fpt;',
            $el->getX(),
            $el->getY(),
            $el->getWidth(),
            $el->getHeight()
        );

        if ($el->getTextAlign() !== '') {
            $style .= 'text-align:' . htmlspecialchars($el->getTextAlign(), ENT_QUOTES, 'UTF-8') . ';';
        }

        $class = 'fu-el';
        if ($el->getBandType() !== '') {
            $class .= ' fu-band-' . StyleMap::sanitize($el->getBandType());
        }

        return sprintf(
            '<div class="%s" style="%s">%s</div>',
            $class,
            $style,
            nl2br(htmlspecialchars($content->getValue(), ENT_QUOTES, 'UTF-8'))
        );
    }

    private function head(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;padding:20pt;background:#e0e0e0;font-family:sans-serif}
.fu-page{position:relative;background:#fff;margin:0 auto 24pt;box-shadow:0 2px 8px rgba(0,0,0,.25);overflow:hidden}
.fu-el{position:absolute;box-sizing:border-box;font-size:9pt;line-height:1.3;overflow:hidden;white-space:pre-wrap}
.fu-band-overlay{position:absolute;left:0;width:100%;pointer-events:none;}
' . $this->styleMap->toCss() . '
@media print{
@page{margin:0}
body{padding:0;background:none}
.fu-page{margin:0;box-shadow:none;break-after:page;page-break-after:always}
.fu-page:last-child{break-after:avoid;page-break-after:avoid}
}
</style>
</head>
<body>
';
    }

    private function foot(): string
    {
        return '</body>
</html>
';
    }
}
