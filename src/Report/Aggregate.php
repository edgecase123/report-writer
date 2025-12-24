<?php

declare(strict_types=1);

namespace ReportWriter\Report;

use Closure;

class Aggregate
{
    public const TYPE_SUM = 'sum';
    public const TYPE_AVG = 'avg';
    public const TYPE_MAX = 'max';
    public const TYPE_MIN = 'min';
    public const TYPE_COUNT = 'count';

    private string $type;
    /** @var string|Closure */
    private $field;
    private float $value = 0.0;
    private int $count = 0;
    private ?float $min = null;
    private ?float $max = null;

    public function __construct(string $type, $field)
    {
        $this->type = $type;
        $this->field = $field;
    }

    public function accumulate($record): void
    {
        $value = is_callable($this->field)
            ? ($this->field)($record)
            : ($record[$this->field] ?? 0);

        // We force float to avoid integer overflow surprises
        $value = (float) $value;

        switch ($this->type) {
            case self::TYPE_SUM:
            case self::TYPE_AVG:
                $this->value += $value;
                $this->count++;
                break;
            case self::TYPE_COUNT:
                $this->count++;
                break;
            case self::TYPE_MIN:
                $this->min = $this->min === null ? $value : min($this->min, $value);
                break;
            case self::TYPE_MAX:
                $this->max = $this->max === null ? $value : max($this->max, $value);
                break;
            default:
                throw new \InvalidArgumentException("Invalid aggregate type: {$this->type}");
        }
    }

    /**
     * Returns the final calculated value of this aggregate.
     */
    public function getValue(): float
    {
        return match ($this->type) {
            self::TYPE_SUM   => $this->value,
            self::TYPE_AVG   => $this->count > 0 ? $this->value / $this->count : 0.0,
            self::TYPE_COUNT => $this->count,
            self::TYPE_MIN   => $this->min ?? 0.0,
            self::TYPE_MAX   => $this->max ?? 0.0,
            default          => throw new \LogicException("Unsupported aggregate type in getValue: {$this->type}"),
        };
    }
}