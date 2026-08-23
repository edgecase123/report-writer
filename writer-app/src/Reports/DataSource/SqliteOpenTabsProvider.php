<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports\DataSource;

use PDO;
use ReportWriter\Interfaces\ReportDataSourceInterface;

final class SqliteOpenTabsProvider implements ReportDataSourceInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * A3.3 is param-less; $params is ignored.
     *
     * @param  array<string, mixed> $params
     * @return array<int, array{order_id: int, opened_at: string, running_total_cents: int}>
     */
    public function fetchRows(array $params): array
    {
        $sql = <<<SQL
            SELECT
                o.id                                                       AS order_id,
                strftime('%Y-%m-%d %H:%M', o.opened_at)                    AS opened_at,
                COALESCE(SUM(oi.quantity * oi.unit_price_cents), 0)        AS running_total_cents
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.closed_at IS NULL
            GROUP BY o.id, o.opened_at
            ORDER BY o.opened_at ASC, o.id ASC
        SQL;

        $stmt = $this->pdo->query($sql);

        return array_map(
            static fn (array $r): array => [
                'order_id'            => (int)    $r['order_id'],
                'opened_at'           => (string) $r['opened_at'],
                'running_total_cents' => (int)    $r['running_total_cents'],
            ],
            $stmt->fetchAll()
        );
    }
}
