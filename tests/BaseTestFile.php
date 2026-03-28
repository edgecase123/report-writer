<?php

declare(strict_types=1);

namespace ReportWriter\Tests;

abstract class BaseTestFile extends \PHPUnit\Framework\TestCase
{
    protected function loadFixture(string $filename): string
    {
        $contents = file_get_contents(__DIR__ . '/Fixtures/' . $filename);
        self::assertNotFalse($contents);

        return $contents;
    }

    protected function loadJsonFixture(string $filename): array
    {
        return json_decode($this->loadFixture($filename), true);
    }
}