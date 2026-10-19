<?php

namespace Josephdlmd\FilamentStorybookPh\Stories;

use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Josephdlmd\FilamentStorybook\Attributes\States;
use Josephdlmd\FilamentStorybook\Documents\DocumentStatus;
use Josephdlmd\FilamentStorybookPh\Documents\PrintedDocument;
use Josephdlmd\FilamentStorybook\Reports\ReportExporter;
use Josephdlmd\FilamentStorybook\Stories;
use Josephdlmd\FilamentStorybookPh\Stories\Documents\PurchaseOrder;
use Josephdlmd\FilamentStorybook\Stories\Reports\PayablesReportExporter;
use Josephdlmd\FilamentStorybookPh\Stories\Reports\PurchasesWithVatReportExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchasesWithVatReportStories extends Stories
{
    public function title(): string
    {
        return 'Reports/Purchases and payables with VAT';
    }

    public function description(): string
    {
        return '`reports-purchases-and-payables` with the VAT and withholding columns of an app that keeps Philippine books, and its exporter. What was bought in a date range (`default`): each posted purchase order with its supplier, the VAT-exclusive amount, the VAT and the total, with a totals row. And what is still owed (`payables`): each order with a balance, its due date and how many days it is past due, oldest first. Built as `reports-sales` is, with its "Download PDF" and "Export" buttons. Basis: the owner\'s business standards (2026-10-12); USWDS table (right-align numbers).';
    }

    public function examples(): array
    {
        return [PurchasesWithVatReportExporter::class, PayablesReportExporter::class, ReportExporter::class];
    }

    #[States('loading', 'empty', 'error', 'forbidden')]
    public function default(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No purchases in this range')
            ->emptyStateDescription('Posted purchase orders dated in the range appear here; change the dates or the supplier to see others.')
            ->query(PurchaseOrder::query()->where('status', DocumentStatus::Posted))
            ->columns([
                TextColumn::make('number')
                    ->label('Order')
                    ->searchable(),
                TextColumn::make('order_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('supplier')
                    ->searchable(),
                TextColumn::make('vatable_purchases')
                    ->label('VATable purchases')
                    ->money('PHP')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('PHP')->label('Total')),
                TextColumn::make('vat')
                    ->label('VAT')
                    ->money('PHP')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('PHP')->label('Total')),
                TextColumn::make('total')
                    ->money('PHP')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('PHP')->label('Total')),
            ])
            ->defaultSort('order_date')
            ->searchPlaceholder('Search by order or supplier')
            ->filters([
                Filter::make('order_date')
                    ->columnSpan(2)
                    ->schema([
                        Fieldset::make('Order date')
                            ->columns(2)
                            ->schema([
                                DatePicker::make('from')
                                    ->maxWidth('xs'),
                                DatePicker::make('until')
                                    ->label('To')
                                    ->maxWidth('xs'),
                            ]),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('order_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('order_date', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_filter([
                        'from' => filled($data['from'] ?? null) ? PrintedDocument::period($data['from'], null) : null,
                        'until' => filled($data['until'] ?? null) ? PrintedDocument::period(null, $data['until']) : null,
                    ])),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->headerActions([
                Action::make('downloadPdf')
                    ->label('Download PDF')
                    ->color('gray')
                    ->action(fn (HasTable $livewire): StreamedResponse => PrintedDocument::download(PrintedDocument::report(
                        'Purchases report',
                        ['Period' => PrintedDocument::period($livewire->getTableFilterState('order_date')['from'] ?? null, $livewire->getTableFilterState('order_date')['until'] ?? null)],
                        [
                            'number' => ['label' => 'Order'],
                            'order_date' => ['label' => 'Date', 'format' => 'date'],
                            'supplier' => ['label' => 'Supplier'],
                            'vatable_purchases' => ['label' => 'VATable purchases', 'format' => 'money'],
                            'vat' => ['label' => 'VAT', 'format' => 'money'],
                            'total' => ['label' => 'Total', 'format' => 'money'],
                        ],
                        $livewire->getFilteredSortedTableQuery()->get(),
                    ))),
                ExportAction::make()
                    ->exporter(PurchasesWithVatReportExporter::class)
                    ->color('gray'),
            ]);
    }

    /** What is still owed to suppliers: every order with a balance, oldest due first. */
    #[States('loading', 'empty', 'error', 'forbidden')]
    public function payables(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Nothing is owed to suppliers')
            ->emptyStateDescription('Posted purchase orders not yet paid in full appear here.')
            ->query(PurchaseOrder::query()->where('status', DocumentStatus::Posted)->where('balance_due', '>', 0))
            ->columns([
                TextColumn::make('number')
                    ->label('Order')
                    ->searchable(),
                TextColumn::make('supplier')
                    ->searchable(),
                TextColumn::make('due_date')
                    ->label('Due date')
                    ->date()
                    ->sortable(),
                TextColumn::make('days_past_due')
                    ->label('Days past due')
                    ->state(fn (?Model $record): ?int => PrintedDocument::daysPastDue($record?->due_date))
                    ->alignEnd(),
                TextColumn::make('balance_due')
                    ->label('Balance')
                    ->money('PHP')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('PHP')->label('Total')),
            ])
            ->defaultSort('due_date')
            ->searchPlaceholder('Search by order or supplier')
            ->headerActions([
                Action::make('downloadPdf')
                    ->label('Download PDF')
                    ->color('gray')
                    ->action(fn (HasTable $livewire): StreamedResponse => PrintedDocument::download(PrintedDocument::report(
                        'Payables',
                        ['As of' => today()->isoFormat('LL')],
                        [
                            'number' => ['label' => 'Order'],
                            'supplier' => ['label' => 'Supplier'],
                            'due_date' => ['label' => 'Due date', 'format' => 'date'],
                            'days_past_due' => ['label' => 'Days past due', 'format' => 'quantity'],
                            'balance_due' => ['label' => 'Balance', 'format' => 'money'],
                        ],
                        $livewire->getFilteredSortedTableQuery()->get()->each(fn (Model $order) => $order->setAttribute('days_past_due', PrintedDocument::daysPastDue($order->due_date))),
                    ))),
                ExportAction::make()
                    ->exporter(PayablesReportExporter::class)
                    ->color('gray'),
            ]);
    }
}
