<?php

namespace Josephdlmd\FilamentStorybookPh\Stories;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Josephdlmd\FilamentStorybook\Attributes\Record;
use Josephdlmd\FilamentStorybook\Attributes\States;
use Josephdlmd\FilamentStorybookPh\Documents\DocumentTotals;
use Josephdlmd\FilamentStorybook\Stories;

class PaperReceiptStories extends Stories
{
    public function title(): string
    {
        return 'Records/Paper receipt';
    }

    public function description(): string
    {
        return 'What staff copy onto the BIR pre-printed collection receipt they write by hand: on the collection\'s View page, a section with each value in the order the paper form asks for it, the amount in words as it is written on a receipt or a check (`DocumentTotals::inWords()`), and every value copyable with one click. The app keeps the record; the paper receipt is the official one, under the serial recorded with the payment (`actions-record-payment`). Put the section first on the View page (`records-view-page`). Business standards from the owner (2026-10-11): collection receipts and sales invoices are written by hand. Basis: Carbon read-only states (Aug 2026: read-only text stays navigable and can be copied); WCAG 1.4.3; USWDS table (numbers right-aligned).';
    }

    public function examples(): array
    {
        return [DocumentTotals::class];
    }

    public function records(): array
    {
        return [1 => [
            'number' => '004518',
            'paid_on' => '2026-10-25',
            'customer_name' => 'Cebu Diagnostics Laboratory',
            'customer_tin' => '987-654-321-000',
            'customer_address' => '45 Osmeña Boulevard, Cebu City 6000',
            'amount' => '6266.41',
            'invoices' => '001232',
            'tax_withheld' => '59.59',
            'vat_withheld' => '0.00',
            'reference' => 'Check 0048213',
        ]];
    }

    /** A collection's details in the paper receipt's order, each one copyable. */
    #[Record]
    #[States('loading', 'error', 'forbidden')]
    public function default(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Write on collection receipt')
                    ->description(fn (?Model $record): string => "Use the pre-printed receipt numbered {$record?->number}")
                    ->inlineLabel()
                    ->schema([
                        TextEntry::make('paid_on')
                            ->label('Date')
                            ->date()
                            ->copyable(),
                        TextEntry::make('customer_name')
                            ->label('Received from')
                            ->copyable(),
                        TextEntry::make('customer_tin')
                            ->label('TIN')
                            ->copyable(),
                        TextEntry::make('customer_address')
                            ->label('Address')
                            ->copyable(),
                        TextEntry::make('amount_in_words')
                            ->label('The sum of')
                            ->state(fn (?Model $record): string => DocumentTotals::inWords((string) $record?->amount))
                            ->copyable(),
                        TextEntry::make('amount')
                            ->label('Amount')
                            ->money('PHP')
                            ->copyable(),
                        TextEntry::make('invoices')
                            ->label('In payment of invoice')
                            ->copyable(),
                        TextEntry::make('tax_withheld')
                            ->label('Income tax withheld')
                            ->money('PHP')
                            ->copyable(),
                        TextEntry::make('vat_withheld')
                            ->label('VAT withheld')
                            ->money('PHP')
                            ->copyable(),
                        TextEntry::make('reference')
                            ->label('Paid by')
                            ->copyable(),
                    ]),
            ]);
    }
}
