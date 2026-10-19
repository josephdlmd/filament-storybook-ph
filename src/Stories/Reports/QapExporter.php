<?php

namespace Josephdlmd\FilamentStorybookPh\Stories\Reports;

use Filament\Actions\Exports\ExportColumn;
use Josephdlmd\FilamentStorybook\Reports\ReportExporter;
use Josephdlmd\FilamentStorybookPh\Stories\Documents\PurchaseOrder;

/**
 * The quarter's QAP (quarterly alphalist of payees, filed with the 1601-EQ) as a CSV or Excel file, one row per supplier
 * and ATC, for the CPA to load into BIR's alphalist tool. Not BIR's DAT file: that format is the alphalist tool's own.
 */
class QapExporter extends ReportExporter
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string $title = 'QAP';

    protected static string $rows = 'payee';

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('supplier_tin')
                ->label('TIN'),
            ExportColumn::make('supplier')
                ->label('Registered name'),
            ExportColumn::make('withholding_code')
                ->label('ATC'),
            ExportColumn::make('withholdingCode.rate')
                ->label('Rate'),
            ExportColumn::make('income_payment')
                ->label('Income payment'),
            ExportColumn::make('tax_withheld')
                ->label('Tax withheld'),
        ];
    }
}
