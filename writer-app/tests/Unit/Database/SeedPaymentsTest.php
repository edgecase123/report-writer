<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use ReportWriter\App\Database\SqliteConnectionFactory;

final class SeedPaymentsTest extends TestCase
{
    public function testEveryClosedOrderHasExactlyOnePayment(): void
    {
        $pdo = $this->seededPdo();

        $closedOrders = (int) $pdo->query(
            'SELECT COUNT(*) FROM orders WHERE closed_at IS NOT NULL'
        )->fetchColumn();
        $paymentCount = (int) $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();

        $this->assertGreaterThan(0, $closedOrders, 'seed should have at least one closed order');
        $this->assertSame(
            $closedOrders,
            $paymentCount,
            'payments must be 1:1 with closed orders (A3.1 v1: single payment per order)'
        );
    }

    public function testPaymentAmountsMatchOrderTotals(): void
    {
        $pdo = $this->seededPdo();

        $mismatches = $pdo->query(
            "SELECT p.order_id
               FROM payments p
               JOIN (
                   SELECT order_id, SUM(quantity * unit_price_cents) AS total_cents
                     FROM order_items
                    GROUP BY order_id
               ) t ON t.order_id = p.order_id
              WHERE p.amount_cents != t.total_cents"
        )->fetchAll();

        $this->assertSame([], $mismatches, 'each payment.amount_cents must equal SUM(order_items) for that order');
    }

    public function testPaymentMethodsAreLimitedToTheThreeAllowed(): void
    {
        $pdo = $this->seededPdo();

        $methods = $pdo->query('SELECT DISTINCT method FROM payments')->fetchAll(\PDO::FETCH_COLUMN);
        sort($methods);
        $this->assertSame(['card', 'cash', 'mobile'], $methods);
    }

    public function testStaffIdIsAValidStaffRow(): void
    {
        $pdo = $this->seededPdo();

        $orphans = (int) $pdo->query(
            'SELECT COUNT(*) FROM payments WHERE staff_id NOT IN (SELECT id FROM staff)'
        )->fetchColumn();

        $this->assertSame(0, $orphans);
    }

    private function seededPdo(): \PDO
    {
        $pdo = SqliteConnectionFactory::createInMemoryWithSchema(
            __DIR__ . '/../../../database/schema.sql'
        );
        require __DIR__ . '/../../../database/seed.php';
        \ReportWriter\App\Database\Seed::run($pdo);
        return $pdo;
    }
}
