<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports\DataSource;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Reports\DataSource\DateParam;

final class DateParamTest extends TestCase
{
    public function testReturnsTheDateStringWhenWellFormed(): void
    {
        $this->assertSame('2026-08-22', DateParam::require(['date' => '2026-08-22']));
    }

    public function testUsesCustomParamNameWhenProvided(): void
    {
        $this->assertSame('2026-08-22', DateParam::require(['from' => '2026-08-22'], 'from'));
    }

    public function testThrowsWhenParamMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Parameter 'date' must be YYYY-MM-DD.");
        DateParam::require([]);
    }

    public function testThrowsWhenParamNotAString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateParam::require(['date' => 20260822]);
    }

    public function testThrowsWhenParamMalformed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateParam::require(['date' => 'yesterday']);
    }

    public function testThrowsWithCustomNameInMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Parameter 'from' must be YYYY-MM-DD.");
        DateParam::require([], 'from');
    }
}
