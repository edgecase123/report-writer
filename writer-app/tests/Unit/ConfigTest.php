<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Config;

final class ConfigTest extends TestCase
{
    public function testAccessorsReturnConstructedValues(): void
    {
        $c = new Config('/tmp/x.sqlite', true);
        $this->assertSame('/tmp/x.sqlite', $c->sqlitePath());
        $this->assertTrue($c->appDebug());
    }

    public function testNullSqlitePathIsPreserved(): void
    {
        $c = new Config(null, false);
        $this->assertNull($c->sqlitePath());
    }

    /**
     * @dataProvider debugTruthiness
     */
    public function testFromEnvReadsDebugTruthily(?string $envValue, bool $expected): void
    {
        if ($envValue === null) {
            putenv('APP_DEBUG');
        } else {
            putenv('APP_DEBUG=' . $envValue);
        }
        putenv('SQLITE_PATH');

        try {
            $this->assertSame($expected, Config::fromEnv()->appDebug());
        } finally {
            putenv('APP_DEBUG');
        }
    }

    public static function debugTruthiness(): array
    {
        return [
            'unset'    => [null, false],
            'empty'    => ['', false],
            'zero'     => ['0', false],
            'one'      => ['1', true],
            'true'     => ['true', true],  // any non-empty non-zero string
        ];
    }

    public function testFromEnvReadsSqlitePathOrNull(): void
    {
        putenv('APP_DEBUG');
        putenv('SQLITE_PATH=/tmp/from-env.sqlite');
        try {
            $this->assertSame('/tmp/from-env.sqlite', Config::fromEnv()->sqlitePath());
        } finally {
            putenv('SQLITE_PATH');
        }

        putenv('SQLITE_PATH');
        $this->assertNull(Config::fromEnv()->sqlitePath());
    }
}
