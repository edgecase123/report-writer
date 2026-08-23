<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports\DataSource;

use PDO;
use ReportWriter\Interfaces\ReportDataSourceInterface;

final class SqliteDailySalesProvider implements ReportDataSourceInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param  array{date?: string} $params
     * @return array<int, array{order_id: int, closed_at: string, total_cents: int}>
     */
    public function fetchRows(array $params): array
    {
        $date = DateParam::require($params);

        $sql = <<<SQL
            SELECT
                o.id                                          AS order_id,
                strftime('%H:%M', o.closed_at)                AS closed_at,
                SUM(oi.quantity * oi.unit_price_cents)        AS total_cents
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            WHERE date(o.closed_at) = :date
              AND o.closed_at IS NOT NULL
            GROUP BY o.id
            ORDER BY o.closed_at ASC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['date' => $date]);

        return array_map(
            static fn (array $r): array => [
                'order_id'    => (int) $r['order_id'],
                'closed_at'   => (string) $r['closed_at'],
                'total_cents' => (int) $r['total_cents'],
            ],
            $stmt->fetchAll()
        );
    }
}
