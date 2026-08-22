<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Expression;

use ReportWriter\Expression\AggregateFunction;
use PHPUnit\Framework\TestCase;

final class AggregateFunctionTest extends TestCase
{
    /** @dataProvider aggregateCases */
    public function testApply(string $fn, array $rows, string $field, float $expected): void
    {
        $this->assertSame($expected, AggregateFunction::apply($fn, $rows, $field));
    }

    public static function aggregateCases(): array
    {
        $rows = [
            ['amount' => 10.0],
            ['amount' => 20.0],
            ['amount' => 30.0],
        ];

        return [
            'sum'         => ['sum',   $rows, 'amount', 60.0],
            'default sum' => ['xxx',   $rows, 'amount', 60.0],
            'avg'         => ['avg',   $rows, 'amount', 20.0],
            'min'         => ['min',   $rows, 'amount', 10.0],
            'max'         => ['max',   $rows, 'amount', 30.0],
            'count'       => ['count', $rows, 'amount', 3.0],
            'empty rows'  => ['sum',   [],    'amount', 0.0],
            'missing field coerced to 0' => ['sum', [['other' => 5]], 'amount', 0.0],
        ];
    }
}
