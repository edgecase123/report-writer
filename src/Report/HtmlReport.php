<?php

namespace ReportWriter\Report;

use Twig\Environment as TwigEnvironment;

class HtmlReport extends AbstractReport
{
    protected function renderBand(string $type, ?int $level = null, $context = null): string
    {
        $name = $level !== null ? $type . '_' . $level : $type;
        $templateName = 'templates/report/' . $name . 'html.twig';

        static $renderer = null;
        if ($renderer === null) {
            $renderer = new TwigRenderer();
        }

        return $renderer->render($templateName, $context ?? []);

    }
}