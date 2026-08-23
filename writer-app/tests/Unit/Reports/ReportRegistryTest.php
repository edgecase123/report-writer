<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports;

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Reports\ParamSpec;
use ReportWriter\App\Reports\ReportDefinition;
use ReportWriter\App\Reports\ReportRegistry;

final class ReportRegistryTest extends TestCase
{
    public function testGetReturnsRegisteredDefinition(): void
    {
        $def = new ReportDefinition(
            'daily-sales',
            'Daily Sales',
            'DailySalesFillerServiceId',
            [new ParamSpec('date', 'date', true)]
        );
        $registry = new ReportRegistry([$def]);

        $this->assertSame($def, $registry->get('daily-sales'));
    }

    public function testGetThrowsWhenIdUnknown(): void
    {
        $registry = new ReportRegistry([]);
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage("Unknown report 'nope'");
        $registry->get('nope');
    }

    public function testAllReturnsRegisteredDefinitionsInInsertionOrder(): void
    {
        $a = new ReportDefinition('a', 'A', 'sid-a', []);
        $b = new ReportDefinition('b', 'B', 'sid-b', []);
        $registry = new ReportRegistry([$a, $b]);

        $this->assertSame([$a, $b], $registry->all());
    }

    public function testParamSpecExposesFields(): void
    {
        $p = new ParamSpec('date', 'date', true);
        $this->assertSame('date', $p->getName());
        $this->assertSame('date', $p->getType());
        $this->assertTrue($p->isRequired());
    }
}
