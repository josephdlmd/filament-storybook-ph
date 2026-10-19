<?php

namespace Josephdlmd\FilamentStorybookPh\Stories\Imports;

use Filament\Actions\Exports\ExportColumn;
use Josephdlmd\FilamentStorybook\Stories\Imports\SupplierExporter;

/** The core supplier export with the TIN and the ATC, the same columns the import reads. */
class SupplierTaxExporter extends SupplierExporter
{
    protected static ?string $model = Supplier::class;

    public static function getColumns(): array
    {
        return [
            ...parent::getColumns(),
            ExportColumn::make('tin')
                ->label('TIN'),
            // A related record's value, by dot notation: the same code the import reads.
            ExportColumn::make('withholdingCode.code')
                ->label('ATC'),
        ];
    }
}
