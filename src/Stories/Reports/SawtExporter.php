<?php

namespace Josephdlmd\FilamentStorybookPh\Stories\Reports;

use Filament\Actions\Exports\ExportColumn;
use Josephdlmd\FilamentStorybook\Reports\ReportExporter;
use Josephdlmd\FilamentStorybook\Stories\Documents\Payment;

/**
 * The quarter's SAWT as a CSV or Excel file, one row per customer TIN and ATC, for the CPA to load into BIR's alphalist
 * tool. Not BIR's DAT file: its layout is BIR's to set, and the alphalist tool makes it.
 */
class SawtExporter extends ReportExporter
{
    protected static ?string $model = Payment::class;

    protected static string $title = 'SAWT';

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('tin')
                ->label('TIN'),
            ExportColumn::make('customer'),
            ExportColumn::make('tax_atc')
                ->label('ATC'),
            ExportColumn::make('income_payment')
                ->label('Income payment'),
            ExportColumn::make('tax_withheld')
                ->label('Income tax withheld'),
            ExportColumn::make('vat_withheld')
                ->label('VAT withheld'),
        ];
    }
}
