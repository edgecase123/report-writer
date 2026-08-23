<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;

final class SeedStaffTest extends TestCase
{
    public function testSeedPopulatesStaffTable(): void
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../database/schema.sql'
        );

        require __DIR__ . '/../../../database/seed.php';
        \ReportWriter\App\Database\Seed::run($pdo);

        $rows = $pdo->query('SELECT id, name, role FROM staff ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);

        // Six-person fixed roster: 4 baristas, 1 shift lead, 1 manager.
        $this->assertCount(6, $rows);
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($rows, 'id'));

        $roleCounts = array_count_values(array_column($rows, 'role'));
        $this->assertSame(4, $roleCounts['barista'] ?? 0);
        $this->assertSame(1, $roleCounts['shift_lead'] ?? 0);
        $this->assertSame(1, $roleCounts['manager'] ?? 0);

        // Every staff row has a non-empty name.
        foreach ($rows as $row) {
            $this->assertNotEmpty($row['name'], 'staff.name must be non-empty');
        }
    }
}
