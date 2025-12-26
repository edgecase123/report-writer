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
        switch ($this->type) {
            case self::TYPE_SUM:
                return $this->value;
            case self::TYPE_AVG:
                return $this->count > 0 ? $this->value / $this->count : 0.0;
            case self::TYPE_COUNT:
                return (float)$this->count;
            case self::TYPE_MIN:
                return $this->min ?? 0.0;
            case self::TYPE_MAX:
                return $this->max ?? 0.0;
            default:
                throw new \LogicException("Unsupported aggregate type in getValue: {$this->type}");
        }
    }
}