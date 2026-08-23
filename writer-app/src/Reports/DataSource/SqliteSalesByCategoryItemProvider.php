<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports\DataSource;

use PDO;
use ReportWriter\Interfaces\ReportDataSourceInterface;

final class SqliteSalesByCategoryItemProvider implements ReportDataSourceInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param  array{date?: string} $params
     * @return array<int, array{category_name: string, item_name: string, quantity_sold: int, total_cents: int}>
     */
    public function fetchRows(array $params): array
    {
        $date = DateParam::require($params);

        $sql = <<<SQL
            SELECT
                c.name                                        AS category_name,
                i.name                                        AS item_name,
                SUM(oi.quantity)                              AS quantity_sold,
                SUM(oi.quantity * oi.unit_price_cents)        AS total_cents
            FROM categories c
            JOIN items       i  ON i.category_id = c.id
            JOIN order_items oi ON oi.item_id    = i.id
            JOIN orders      o  ON o.id          = oi.order_id
            WHERE date(o.closed_at) = :date
              AND o.closed_at IS NOT NULL
            GROUP BY c.id, c.name, i.id, i.name
            ORDER BY c.name ASC, total_cents DESC, i.name ASC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['date' => $date]);

        return array_map(
            static fn (array $r): array => [
                'category_name' => (string) $r['category_name'],
                'item_name'     => (string) $r['item_name'],
                'quantity_sold' => (int)    $r['quantity_sold'],
                'total_cents'   => (int)    $r['total_cents'],
            ],
            $stmt->fetchAll()
        );
    }
}
