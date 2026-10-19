<?php

namespace Josephdlmd\FilamentStorybookPh\Stories\Imports;

use Filament\Actions\Imports\ImportColumn;
use Illuminate\Validation\Rule;
use Josephdlmd\FilamentStorybook\Stories\Imports\SupplierImporter;
use Josephdlmd\FilamentStorybookPh\Stories\Documents\WithholdingCode;

/** The core supplier import with the TIN, which a row matches on, and the default ATC. */
class SupplierTaxImporter extends SupplierImporter
{
    protected static ?string $model = Supplier::class;

    public static function getColumns(): array
    {
        return [
            ...parent::getColumns(),
            // The same check and stored format as the form's TIN field (forms-tin): only 12 digits with spaces, dashes or dots are
            // formatted; anything else stays as typed, so the rule refuses it.
            ImportColumn::make('tin')
                ->label('TIN')
                ->requiredMapping()
                ->castStateUsing(fn (?string $state): ?string => filled($state) && preg_match('/^[\s.-]*(\d[\s.-]*){12}$/', $state) ? implode('-', str_split(preg_replace('/\D/', '', $state), 3)) : $state)
                ->rules(['required', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/'])
                ->example('123-456-789-000'),
            // A related record, by the value people know it by (an id for most relationships would be a name or code). The
            // rule runs first and stops there (bail), so an unknown code gets the message below, not Filament's "is invalid".
            ImportColumn::make('withholdingCode')
                ->label('ATC')
                ->relationship()
                ->castStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(trim($state)) : null)
                ->rules(['bail', 'nullable', Rule::exists(WithholdingCode::class, 'code')->where('withheld_by', 'us')])
                ->example('WC158'),
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            ...parent::getValidationMessages(),
            'tin.required' => 'Enter the TIN',
            'tin.regex' => 'Enter the TIN as 12 digits, such as 123-456-789-000',
            'withholdingCode.exists' => 'Enter an ATC we withhold, such as WC158, or leave it empty',
        ];
    }

    /** A row whose TIN is on file updates that supplier; any other row adds one. */
    public function resolveRecord(): Supplier
    {
        return Supplier::firstOrNew(['tin' => $this->data['tin']]);
    }
}
