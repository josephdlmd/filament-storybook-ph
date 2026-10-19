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
use Josephdlmd\FilamentStorybook\Attributes\Section;
use Josephdlmd\FilamentStorybook\Attributes\States;
use Josephdlmd\FilamentStorybookPh\Documents\PrintedDocument;
use Josephdlmd\FilamentStorybook\Reports\ReportExporter;
use Josephdlmd\FilamentStorybook\Stories;
use Josephdlmd\FilamentStorybook\Stories\Documents\Payment;
use Josephdlmd\FilamentStorybookPh\Stories\Reports\CustomerWithholding;
use Josephdlmd\FilamentStorybookPh\Stories\Reports\SawtExporter;
use Josephdlmd\FilamentStorybookPh\Stories\Reports\SupplierWithholding;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerWithholdingStories extends Stories
{
    public function title(): string
    {
        return 'Reports/Tax withheld by customers';
    }

    public function description(): string
    {
        return 'The SAWT, the summary of the tax customers withheld from us that goes with each quarter\'s returns (`default`): one row per customer TIN and ATC, with the income payment, the income tax withheld and the VAT withheld in their own columns, and a totals row. Withholding is claimed in the quarter it was paid, whether or not its 2307 has arrived, so the SAWT and the quarter\'s return agree; `awaiting` lists the payments whose 2307 is still to collect, oldest first, and `actions-mark-2307-received` records each 2307 as it arrives. Built as `reports-sales` is: a page under Reports, a quarter filter (the quarter just ended until the user picks another, as `reports-supplier-withholding` has it), "Download PDF" and "Export" (a CSV or Excel file for the CPA to load into BIR\'s alphalist tool, not BIR\'s DAT file, which that tool makes). Quarters go by the payment date. Basis: the owner\'s business standards (2026-10-18: customer withholding claimed in the quarter paid; 2026-10-12: the QAP and SAWT produced for the CPA); RR 2-98 Sec. 2.58.3 (the tax withheld is credited in the quarter the income was received, supported by the 2307); USWDS table (right-align numbers).';
    }

    public function examples(): array
    {
        return [CustomerWithholding::class, SawtExporter::class, ReportExporter::class];
    }

    #[States('loading', 'empty', 'error', 'forbidden')]
    public function default(Table $table): Table
    {
        // The filter's quarter, or the one just ended.
        $quarter = fn (?array $data): string => $data['quarter'] ?? null ?: SupplierWithholding::lastQuarter();

        return $table
            ->emptyStateHeading('No tax withheld in this quarter')
            ->emptyStateDescription('Payments whose customer withheld tax appear here, in the quarter they were paid.')
            ->query(Payment::query())
            ->columns([
                TextColumn::make('tin')
                    ->label('TIN')
                    ->searchable(),
                TextColumn::make('customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tax_atc')
                    ->label('ATC'),
                TextColumn::make('income_payment')
                    ->label('Income payment')
                    ->money('PHP')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('PHP')->label('Total')),
                TextColumn::make('tax_withheld')
                    ->label('Income tax withheld')
                    ->money('PHP')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('PHP')->label('Total')),
                TextColumn::make('vat_withheld')
                    ->label('VAT withheld')
                    ->money('PHP')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('PHP')->label('Total')),
            ])
            ->defaultSort('customer')
            ->searchPlaceholder('Search by TIN or customer')
            ->filters([
                Filter::make('quarter')
                    ->schema([
                        Select::make('quarter')
                            ->options(SupplierWithholding::quarters())
                            ->default(SupplierWithholding::lastQuarter())
                            ->selectablePlaceholder(false)
                            ->maxWidth('xs'),
                    ])
                    // The rows are grouped in a subquery for the quarter: baseQuery() sets it, even with the filter empty, before
                    // the search adds its conditions, so the rows, the totals row and the export agree.
                    ->baseQuery(fn (Builder $query, array $data) => CustomerWithholding::sawt($quarter($data), payments: $query))
                    ->indicateUsing(fn (array $data): ?string => SupplierWithholding::quarters()[$data['quarter'] ?? ''] ?? null),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->headerActions([
                Action::make('downloadPdf')
                    ->label('Download PDF')
                    ->color('gray')
                    ->action(fn (HasTable $livewire): StreamedResponse => PrintedDocument::download(PrintedDocument::report(
                        'Tax withheld by customers',
                        ['Quarter' => SupplierWithholding::quarters()[$quarter($livewire->getTableFilterState('quarter'))] ?? $quarter($livewire->getTableFilterState('quarter'))],
                        [
                            'tin' => ['label' => 'TIN'],
                            'customer' => ['label' => 'Customer'],
                            'tax_atc' => ['label' => 'ATC'],
                            'income_payment' => ['label' => 'Income payment', 'format' => 'money'],
                            'tax_withheld' => ['label' => 'Income tax withheld', 'format' => 'money'],
                            'vat_withheld' => ['label' => 'VAT withheld', 'format' => 'money'],
                        ],
                        $livewire->getFilteredSortedTableQuery()->get(),
                    ))),
                ExportAction::make()
                    ->exporter(SawtExporter::class)
                    ->color('gray'),
            ]);
    }

    /** The 2307s still to collect: posted payments whose 2307 hasn't arrived, oldest first. */
    #[Section]
    #[States('loading', 'empty')]
    public function awaiting(Table $table): Table
    {
        return $table
            ->query(CustomerWithholding::awaiting()->with('salesInvoice.customer'))
            ->heading('Waiting for a 2307')
            ->columns([
                TextColumn::make('number')
                    ->label('Receipt'),
                TextColumn::make('paid_on')
                    ->label('Paid on')
                    ->date(),
                TextColumn::make('salesInvoice.customer.name')
                    ->label('Customer'),
                TextColumn::make('tax_withheld')
                    ->label('Income tax withheld')
                    ->money('PHP')
                    ->alignEnd(),
                TextColumn::make('vat_withheld')
                    ->label('VAT withheld')
                    ->money('PHP')
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Every 2307 is in')
            ->emptyStateDescription('Payments whose customer withheld tax appear here until their 2307 arrives.');
    }
}
