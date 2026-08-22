<?php

declare(strict_types=1);

namespace ReportWriter\Registry;

class FormatterRegistry
{
    /** @var array<string, callable> */
    private array $formatters;

    /** @param array<string, callable> $formatters */
    public function __construct(array $formatters = [])
    {
        $this->formatters = $formatters;
    }

    public static function defaults(): self
    {
        return new self([
            'currency' => static function ($v): string {
                return '$' . number_format((float) $v, 2);
            },
            'cents' => static function ($v): string {
                return '$' . number_format((float) $v / 100, 2);
            },
            'integer' => static function ($v): string {
                return number_format((int) $v, 0);
            },
            'date' => static function ($v): string {
                $d = \DateTime::createFromFormat('Y-m-d', (string) $v);
                return $d !== false ? $d->format('M j, Y') : (string) $v;
            },
        ]);
    }

    public function register(string $name, callable $fn): void
    {
        $this->formatters[$name] = $fn;
    }

    public function get(string $name): callable
    {
        if (!isset($this->formatters[$name])) {
            throw new \InvalidArgumentException("Unknown formatter: '{$name}'");
        }
        return $this->formatters[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->formatters[$name]);
    }
}
