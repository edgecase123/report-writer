<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;

final class SqliteConnectionFactoryTest extends TestCase
{
    public function testCreatesInMemoryPdoWithSchemaLoaded(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../database/schema.sql'
        );

        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")
                       ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(['categories', 'items', 'order_items', 'orders', 'payments', 'staff'], $tables);
    }

    public function testCreatesFileBackedPdo(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rw-a2-') . '.sqlite';
        try {
            $pdo = SqliteConnectionFactory::createFromPath($tmp);
            $this->assertInstanceOf(PDO::class, $pdo);
            $this->assertFileExists($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
