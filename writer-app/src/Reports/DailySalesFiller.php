<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports;

use ReportWriter\Builder\Column;
use ReportWriter\Builder\ReportBuilder;
use ReportWriter\Instance\ReportInstance;
use ReportWriter\Interfaces\ReportDataSourceInterface;
use ReportWriter\Interfaces\ReportFillerInterface;
use ReportWriter\Registry\FormatterRegistry;

final class DailySalesFiller implements ReportFillerInterface
{
    private ReportDataSourceInterface $provider;

    public function __construct(ReportDataSourceInterface $provider)
    {
        $this->provider = $provider;
    }

    public function fill(array $params): ReportInstance
    {
        $date     = $params['date'] ?? '';
        $rows     = $this->provider->fetchRows($params);
        $currency = FormatterRegistry::defaults()->get('cents');

        return ReportBuilder::create('daily-sales')
            ->title("Daily Sales — {$date}")
            ->columns([
                Column::make('order_id',    'Order',       0,   120),
                Column::make('closed_at',   'Closed',      130, 120),
                Column::make('total_cents', 'Total',       260, 120)
                    ->sum()
                    ->alignRight()
                    ->format($currency),
            ])
            ->rows($rows)
            ->build();
    }
}
