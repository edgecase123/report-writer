<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports\DataSource;

use InvalidArgumentException;
use PDO;
use ReportWriter\Interfaces\ReportDataSourceInterface;

final class SqliteSalesByCategoryProvider implements ReportDataSourceInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param  array{date?: string} $params
     * @return array<int, array{category_name: string, total_cents: int}>
     */
    public function fetchRows(array $params): array
    {
        $date = $params['date'] ?? null;
        if (!is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new InvalidArgumentException("Parameter 'date' must be YYYY-MM-DD; got " . var_export($date, true));
        }

        $sql = <<<SQL
            SELECT
                c.name                                        AS category_name,
                SUM(oi.quantity * oi.unit_price_cents)        AS total_cents
            FROM categories c
            JOIN items       i  ON i.category_id = c.id
            JOIN order_items oi ON oi.item_id    = i.id
            JOIN orders      o  ON o.id          = oi.order_id
            WHERE date(o.closed_at) = :date
              AND o.closed_at IS NOT NULL
            GROUP BY c.id, c.name
            ORDER BY total_cents DESC, c.name ASC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['date' => $date]);

        return array_map(
            static fn (array $r): array => [
                'category_name' => (string) $r['category_name'],
                'total_cents'   => (int)    $r['total_cents'],
            ],
            $stmt->fetchAll()
        );
    }
}
