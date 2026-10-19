<?php

namespace Josephdlmd\FilamentStorybookPh\Stories\Reports;

use Filament\Actions\Exports\ExportColumn;
use Josephdlmd\FilamentStorybook\Reports\ReportExporter;
use Josephdlmd\FilamentStorybook\Stories\Documents\Payment;

/** The collections report as a CSV or Excel file, with the report's filters. */
class CollectionsWithWithholdingReportExporter extends ReportExporter
{
    protected static ?string $model = Payment::class;

    protected static string $title = 'Collections report';

    protected static string $rows = 'payment';

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('number')
                ->label('Receipt'),
            ExportColumn::make('paid_on')
                ->label('Date'),
            ExportColumn::make('salesInvoice.customer.name')
                ->label('Customer'),
            ExportColumn::make('salesInvoice.number')
                ->label('Invoice'),
            ExportColumn::make('amount')
                ->label('Amount received'),
            ExportColumn::make('tax_withheld')
                ->label('Income tax withheld'),
            ExportColumn::make('vat_withheld')
                ->label('VAT withheld'),
        ];
    }
}
