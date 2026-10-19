<?php

namespace Josephdlmd\FilamentStorybookPh\Stories\Reports;

use Filament\Actions\Exports\ExportColumn;
use Josephdlmd\FilamentStorybook\Reports\ReportExporter;
use Josephdlmd\FilamentStorybookPh\Stories\Documents\PurchaseOrder;

/** The purchases report as a CSV or Excel file, with the report's filters. */
class PurchasesWithVatReportExporter extends ReportExporter
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string $title = 'Purchases report';

    protected static string $rows = 'order';

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('number')
                ->label('Order'),
            ExportColumn::make('order_date')
                ->label('Date'),
            ExportColumn::make('supplier'),
            ExportColumn::make('vatable_purchases')
                ->label('VATable purchases'),
            ExportColumn::make('vat')
                ->label('VAT'),
            ExportColumn::make('total'),
        ];
    }
}
