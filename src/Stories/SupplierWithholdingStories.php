<?php

namespace Josephdlmd\FilamentStorybookPh\Stories;

use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Josephdlmd\FilamentStorybook\Attributes\States;
use Josephdlmd\FilamentStorybookPh\Documents\PrintedDocument;
use Josephdlmd\FilamentStorybook\Reports\ReportExporter;
use Josephdlmd\FilamentStorybook\Stories;
use Josephdlmd\FilamentStorybookPh\Stories\Documents\PurchaseOrder;
use Josephdlmd\FilamentStorybookPh\Stories\Reports\QapExporter;
use Josephdlmd\FilamentStorybookPh\Stories\Reports\SupplierWithholding;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierWithholdingStories extends Stories
{
    public function title(): string
    {
        return 'Reports/Tax withheld from suppliers';
    }

    public function description(): string
    {
        return 'The tax we withheld from suppliers in a quarter, one row per supplier and ATC, with the income payments of each month of the quarter (each invoice\'s total less VAT), their total and the tax withheld, as BIR Form 2307 lists them, and a totals row. The quarter starts at the one just ended, whose 2307s are due. Each row\'s "Download 2307 details" gives that supplier\'s whole quarter, every ATC, in the shared layout, for staff to copy onto BIR\'s Form 2307, which stays the certificate the payor signs; the app doesn\'t reproduce BIR\'s form. "Export" is the QAP, the quarterly alphalist of payees filed with the 1601-EQ, as a CSV or Excel file for the CPA to load into BIR\'s alphalist tool, not BIR\'s DAT file. Built as `reports-sales` is, for roles with "view reports". `SupplierWithholding` groups the quarter in the database, so the totals row adds it; a filter can\'t change where rows come from, so the query reads the quarter itself. Basis: the owner\'s business standards (2026-10-12: one 2307 per supplier per quarter; the QAP for the CPA; withholding when the supplier\'s invoice is recorded, RR 4-2024); USWDS table (right-align numbers).';
    }

    public function examples(): array
    {
        return [SupplierWithholding::class, QapExporter::class, ReportExporter::class];
    }

    #[States('loading', 'empty', 'error', 'forbidden')]
    public function default(Table $table): Table
    {
        // The quarter the user picked, or the one just ended.
        $quarter = fn (HasTable $livewire): string => $livewire->getTableFilterState('quarter')['quarter'] ?? SupplierWithholding::lastQuarter();

        return $table
            ->emptyStateHeading('No tax withheld in this quarter')
            ->emptyStateDescription('Payments to suppliers you withheld tax from appear here, in the quarter they were paid.')
            ->query(PurchaseOrder::query())
            ->columns([
                TextColumn::make('supplier')
                    ->searchable(),
                TextColumn::make('supplier_tin')
                    ->label('TIN'),
                TextColumn::make('withholding_code')
                    ->label('ATC'),
                ...collect(['month_1' => '1st month', 'month_2' => '2nd month', 'month_3' => '3rd month', 'income_payment' => 'Income payment', 'tax_withheld' => 'Tax withheld'])
                    ->map(fn (string $label, string $column): TextColumn => TextColumn::make($column)
                        ->label($label)
                        ->money('PHP')
                        ->alignEnd()
                        ->summarize(Sum::make()->money('PHP')->label('Total')))
                    ->values()
                    ->all(),
            ])
            ->defaultSort('supplier')
            ->searchPlaceholder('Search by supplier')
            ->filters([
                Filter::make('quarter')
                    ->schema([
                        Select::make('quarter')
                            ->options(SupplierWithholding::quarters())
                            ->default(SupplierWithholding::lastQuarter())
                            ->selectablePlaceholder(false)
                            ->maxWidth('xs'),
                    ])
                    // The rows are grouped in a subquery for the quarter: baseQuery() sets it, even with the filter empty.
                    ->baseQuery(fn (Builder $query, array $data) => SupplierWithholding::quarterQuery($query, $data['quarter'] ?? null ?: SupplierWithholding::lastQuarter()))
                    ->indicateUsing(fn (array $data): ?string => SupplierWithholding::quarters()[$data['quarter'] ?? ''] ?? null),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->headerActions([
                Action::make('downloadPdf')
                    ->label('Download PDF')
                    ->color('gray')
                    ->action(fn (HasTable $livewire): StreamedResponse => PrintedDocument::download(PrintedDocument::report(
                        'Tax withheld from suppliers',
                        ['Quarter' => SupplierWithholding::quarters()[$quarter($livewire)] ?? $quarter($livewire)],
                        [
                            'supplier' => ['label' => 'Supplier'],
                            'supplier_tin' => ['label' => 'TIN'],
                            'withholding_code' => ['label' => 'ATC'],
                            'month_1' => ['label' => '1st month', 'format' => 'money'],
                            'month_2' => ['label' => '2nd month', 'format' => 'money'],
                            'month_3' => ['label' => '3rd month', 'format' => 'money'],
                            'income_payment' => ['label' => 'Income payment', 'format' => 'money'],
                            'tax_withheld' => ['label' => 'Tax withheld', 'format' => 'money'],
                        ],
                        $livewire->getFilteredSortedTableQuery()->get(),
                    ))),
                ExportAction::make()
                    ->label('Export QAP')
                    ->exporter(QapExporter::class)
                    ->color('gray'),
            ])
            ->recordActions([
                // One 2307 per supplier per quarter: every ATC of that supplier, not only this row's.
                Action::make('download2307')
                    ->label('Download 2307 details')
                    ->color('gray')
                    ->action(fn (Model $record, HasTable $livewire): StreamedResponse => PrintedDocument::download(PrintedDocument::form2307Details(
                        $record->supplier,
                        $record->supplier_tin,
                        $record->supplier_address,
                        SupplierWithholding::start($quarter($livewire)),
                        SupplierWithholding::quarterQuery(PurchaseOrder::query(), $quarter($livewire))->where('supplier_tin', $record->supplier_tin)->get(),
                    ))),
            ]);
    }
}
