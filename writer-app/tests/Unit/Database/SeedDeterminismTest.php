<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;

final class SeedDeterminismTest extends TestCase
{
    public function testSeedProducesByteIdenticalRowsAcrossRuns(): void
    {
        $rowsA = $this->seedAndDump();
        $rowsB = $this->seedAndDump();

        $this->assertSame($rowsA, $rowsB, 'seed must be byte-identical across runs (ADR-002 mt_srand(1))');
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function seedAndDump(): array
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../database/schema.sql'
        );

        require __DIR__ . '/../../../database/seed.php';
        \ReportWriter\App\Database\Seed::run($pdo);

        return [
            'categories'  => $pdo->query('SELECT * FROM categories  ORDER BY id')->fetchAll(),
            'items'       => $pdo->query('SELECT * FROM items       ORDER BY id')->fetchAll(),
            'orders'      => $pdo->query('SELECT * FROM orders      ORDER BY id')->fetchAll(),
            'order_items' => $pdo->query('SELECT * FROM order_items ORDER BY id')->fetchAll(),
        ];
    }
}
