<?php

declare(strict_types=1);

namespace ReportWriter\App\Database;

use PDO;
use RuntimeException;

final class SqliteConnectionFactory
{
    public static function createFromPath(string $path): PDO
    {
        return self::configure(new PDO('sqlite:' . $path));
    }

    public static function createInMemoryWithSchema(string $schemaFile): PDO
    {
        $pdo = self::configure(new PDO('sqlite::memory:'));
        self::loadSchema($pdo, $schemaFile);
        return $pdo;
    }

    public static function loadSchema(PDO $pdo, string $schemaFile): void
    {
        if (!is_readable($schemaFile)) {
            throw new RuntimeException("Schema file not readable: {$schemaFile}");
        }
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
    }

    private static function configure(PDO $pdo): PDO
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }
}
