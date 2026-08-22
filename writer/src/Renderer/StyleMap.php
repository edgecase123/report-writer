<?php

declare(strict_types=1);

namespace ReportWriter\Renderer;

/**
 * Maps band types to CSS property strings applied by the HtmlRenderer.
 *
 * Keys are the band_type strings set by fillers (e.g. 'title', 'col-header').
 * Values are arrays of CSS property => value pairs.
 *
 * Inject a custom StyleMap via DI to enforce a consistent look across all reports.
 */
class StyleMap
{
    /** @var array<string, array<string, string>> */
    private array $elementMap;

    /** @var array<string, array<string, string>> */
    private array $bandMap;

    /**
     * @param array<string, array<string, string>> $elementMap  Per-element CSS applied to each fu-el div.
     * @param array<string, array<string, string>> $bandMap     Per-band CSS applied to a full-width overlay div.
     */
    public function __construct(array $elementMap = [], array $bandMap = [])
    {
        $this->elementMap = $elementMap;
        $this->bandMap    = $bandMap;
    }

    public static function defaults(): self
    {
        return new self(
            // Element-level styles
            [
                'title' => [
                    'font-size'   => '14pt',
                    'font-weight' => 'bold',
                    'text-align'  => 'center',
                    'color'       => '#1a1a2e',
                ],
                'col-header' => [
                    'font-weight' => 'bold',
                ],
                'group-header' => [
                    'font-weight' => 'bold',
                    'color'       => '#1a1a2e',
                ],
                'group-footer' => [
                    'font-style' => 'italic',
                    'color'      => '#555555',
                ],
                'summary' => [
                    'color'      => '#555555',
                    'font-style' => 'italic',
                ],
            ],
            // Band-level styles (applied to a full-width overlay div)
            [
                'col-header' => ['border-bottom' => '1px solid #333'],
            ]
        );
    }

    /** @return array<string, string> */
    public function getStyleFor(string $bandType): array
    {
        return $this->elementMap[$bandType] ?? [];
    }

    /** @return array<string, string> */
    public function getBandStyleFor(string $bandType): array
    {
        return $this->bandMap[$bandType] ?? [];
    }

    public function with(string $bandType, array $styles): self
    {
        $clone = clone $this;
        $clone->elementMap[$bandType] = $styles;
        return $clone;
    }

    public function merge(string $bandType, array $styles): self
    {
        $clone = clone $this;
        $clone->elementMap[$bandType] = array_merge($clone->elementMap[$bandType] ?? [], $styles);
        return $clone;
    }

    public function toCss(): string
    {
        $css = '';
        foreach ($this->elementMap as $bandType => $styles) {
            $css .= '.fu-band-' . self::sanitize($bandType) . '{';
            foreach ($styles as $prop => $value) {
                $css .= $prop . ':' . $value . ';';
            }
            $css .= '}';
        }
        foreach ($this->bandMap as $bandType => $styles) {
            $css .= '.fu-band-overlay-' . self::sanitize($bandType) . '{';
            foreach ($styles as $prop => $value) {
                $css .= $prop . ':' . $value . ';';
            }
            $css .= '}';
        }
        return $css;
    }

    public static function sanitize(string $bandType): string
    {
        return preg_replace('/[^a-z0-9-]/', '-', strtolower($bandType));
    }
}
