<?php

namespace Josephdlmd\FilamentStorybookPh\Stories\Reports;

use Filament\Actions\Exports\ExportColumn;
use Josephdlmd\FilamentStorybook\Reports\ReportExporter;
use Josephdlmd\FilamentStorybook\Stories\Documents\SalesInvoice;

/**
 * The sales report as a CSV or Excel file: the same columns as on screen, amounts as plain numbers so a spreadsheet can
 * add them. The export takes the report's filters, so it holds what the screen shows. Copy it into `app/Filament/Exports`,
 * one per report, with `ReportExporter`.
 */
class SalesWithVatReportExporter extends ReportExporter
{
    protected static ?string $model = SalesInvoice::class;

    protected static string $title = 'Sales report';

    protected static string $rows = 'invoice';

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('number')
                ->label('Invoice'),
            ExportColumn::make('invoice_date')
                ->label('Date'),
            ExportColumn::make('customer.name')
                ->label('Customer'),
            ExportColumn::make('vatable_sales')
                ->label('VATable sales'),
            ExportColumn::make('vat')
                ->label('VAT'),
            ExportColumn::make('zero_rated_sales')
                ->label('Zero-rated sales'),
            ExportColumn::make('exempt_sales')
                ->label('VAT-exempt sales'),
            ExportColumn::make('total')
                ->label('Total'),
        ];
    }
}
