<?php

declare(strict_types=1);

namespace ReportWriter\App\Reports;

use ReportWriter\Builder\Column;
use ReportWriter\Builder\ReportBuilder;
use ReportWriter\Instance\ReportInstance;
use ReportWriter\Interfaces\ReportDataSourceInterface;
use ReportWriter\Interfaces\ReportFillerInterface;
use ReportWriter\Registry\FormatterRegistry;

final class SalesByCategoryItemFiller implements ReportFillerInterface
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

        return ReportBuilder::create('sales-by-category-item')
            ->title("Sales by Category → Item — {$date}")
            ->columns([
                Column::make('item_name',     'Item',  0,   300)->alignLeft(),
                Column::make('quantity_sold', 'Qty',   300,  90)->alignRight()->sum(),
                Column::make('total_cents',   'Total', 390,  90)
                    ->alignRight()
                    ->sum()
                    ->format($currency),
            ])
            ->rows($rows)
            ->groupBy('category_name')
            ->build();
    }
}
