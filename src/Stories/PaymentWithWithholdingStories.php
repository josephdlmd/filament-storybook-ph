<?php

namespace Josephdlmd\FilamentStorybookPh\Stories;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Number;
use Josephdlmd\FilamentStorybook\Documents\DocumentStatus;
use Josephdlmd\FilamentStorybookPh\Documents\DocumentTotals;
use Josephdlmd\FilamentStorybook\Documents\PaymentStatus;
use Josephdlmd\FilamentStorybook\Stories;
use Josephdlmd\FilamentStorybook\Stories\Documents\Payment;
use Josephdlmd\FilamentStorybook\Stories\Documents\SalesInvoice;
use Josephdlmd\FilamentStorybookPh\Stories\Documents\WithholdingCode;

class PaymentWithWithholdingStories extends Stories
{
    public function title(): string
    {
        return 'Actions/Record payment with withholding';
    }

    public function description(): string
    {
        return '`actions-record-payment` for an app that keeps Philippine books, with the tax withheld. Recording a payment, in full or in part, against a posted document\'s balance due: a collection from a customer against a sales invoice (`default`), or a payment to a supplier against a purchase order (`supplierPayment`). Put it in the View page\'s `getHeaderActions()`, shown while a balance is due; each payment is a record of its own, listed on the document\'s View page (`records-related-records`). The collection form has seven inputs, so it opens as a slide-over; the supplier payment is a modal. Each has the balance due in the amount\'s helper text, and what the payment settles is checked against the balance on the server; the error says the most it can be. A collection is written by hand on a BIR pre-printed collection receipt, so it is recorded under that receipt\'s serial, checked against the serials already used (in a resource, `->unique(Payment::class, \'number\', ignoreRecord: false)` with the app\'s own model, since Filament otherwise skips the row whose id matches the invoice\'s; the story checks the sample one), and staff copy the receipt from its View page (`records-paper-receipt`). What the customer withholds depends on who they are, set on the customer (`customerWithholding`): a government office withholds 1% income tax (EWT) on the VAT-exclusive amount and 5% VAT on the VATable sales (exempt and zero-rated sales carry none), with no income tax on a single purchase of ₱10,000 or less, a top withholding agent 1% income tax, others nothing. The collection proposes both from the balance due with `DocumentTotals::customerRates()`, and the cash expected, and the user records what the customer\'s 2307 shows, with the income tax\'s ATC. Each payment keeps its income payment, the VAT-exclusive share of what it settles (`DocumentTotals::incomePayment()`), and that ATC, which the SAWT adds up (`reports-tax-withheld-by-customers`). Both are creditable: the income tax against income tax, the VAT withheld (ATC WV010) against output VAT, on the VAT return only, so they are kept apart, each with its ATC. They are recorded with the payment even before the 2307 arrives; keep a `received_2307_on` date on the payment, which can still be set after posting (`records-locked`), and a list of payments still waiting for one. A supplier\'s withholding is worked out once on their invoice (`forms-document-totals-with-vat.supplierInvoice`), so a supplier payment only settles the net payable, under the next PV number (`records-document-lifecycle.withSeries`). When saving, lock the document\'s row, check again that it is still posted and that the amounts fit its balance (two users may record payments, or void it, at once), then lower its balance due, all in one transaction. Give `payments.number` a unique index: it is the real guard against a serial two users enter at once, and the action catches its error, keeps the form open and says what to check. The balance\'s status is Unpaid (gray), Partly paid (blue), Paid (green) or Overdue (red), each with its word (`paymentStatus`). Business standards from the owner (2026-10-11). Basis: Carbon forms (Aug 2026: a dialog form for fewer than five inputs, a side panel for more than five), GOV.UK error message (Jan 2024: say the limit, as in "must be 35 characters or less"), Carbon status indicators (Aug 2026: gray for not started, blue for workflow progress, green for success, red for danger), USWDS table (numbers right-aligned).';
    }

    // Not imported, so the snippet's imports stay what a resource needs.
    public function examples(): array
    {
        return [PaymentStatus::class];
    }

    public function model(): ?string
    {
        return SalesInvoice::class;
    }

    public function records(): array
    {
        return [
            1 => ['id' => 1, 'status' => DocumentStatus::Posted, 'number' => '001232', 'total' => '112000.00', 'vat' => '12000.00', 'vatable_sales' => '100000.00', 'balance_due' => '112000.00', 'customer_withholding' => 'government', 'payment_status' => PaymentStatus::Unpaid, 'supplier' => 'Benguet Trading', 'tax_withheld' => '47.30'],
            2 => ['status' => DocumentStatus::Posted, 'number' => '001233', 'total' => '3363.71', 'vat' => '360.40', 'vatable_sales' => '3003.31', 'balance_due' => '2363.71', 'customer_withholding' => 'top_withholding_agent', 'payment_status' => PaymentStatus::PartlyPaid],
            3 => ['status' => DocumentStatus::Posted, 'number' => '001229', 'total' => '5600.00', 'balance_due' => '0.00', 'payment_status' => PaymentStatus::Paid],
            4 => ['status' => DocumentStatus::Posted, 'number' => '001214', 'total' => '890.50', 'balance_due' => '890.50', 'payment_status' => PaymentStatus::Overdue],
        ];
    }

    /** A collection from a customer, under the serial of the receipt it was written on, with what the customer withheld proposed from their withholding type. */
    public function default(): Action
    {
        return Action::make('recordPayment')
            ->label('Record payment')
            ->visible(fn (?Model $record): bool => $record?->status === DocumentStatus::Posted && bccomp((string) $record?->balance_due, '0', 2) > 0)
            ->slideOver()
            ->modalHeading(fn (?Model $record): string => "Record payment for invoice {$record?->number}")
            ->schema([
                DatePicker::make('paid_on')
                    ->label('Payment date')
                    ->default(today())
                    ->maxDate(today())
                    ->required()
                    ->maxWidth('xs'),
                TextInput::make('number')
                    ->label('Receipt number')
                    ->helperText('The serial printed on the collection receipt you wrote')
                    ->required()
                    ->maxLength(20)
                    ->unique(Payment::class, 'number', ignoreRecord: false)
                    ->maxWidth('xs')
                    ->validationMessages(['unique' => 'Enter the serial of the receipt you wrote; receipt :input is already recorded']),
                TextInput::make('amount')
                    ->label('Amount received')
                    ->numeric()
                    ->prefix('₱')
                    ->step(0.01)
                    ->minValue(0)
                    ->required()
                    ->maxWidth('xs')
                    ->default(function (?Model $record): string {
                        $rates = DocumentTotals::customerRates($record?->customer_withholding, (string) $record?->total);
                        $withheld = fn (string $base, string $rate): string => DocumentTotals::expectedWithholding((string) $record?->balance_due, (string) $record?->total, $base, $rate);

                        return bcsub(bcsub((string) $record?->balance_due, $withheld(bcsub((string) $record?->total, (string) $record?->vat, 2), $rates['income_tax']), 2), $withheld((string) $record?->vatable_sales, $rates['vat']), 2);
                    })
                    ->helperText(fn (?Model $record): string => 'Balance due '.Number::currency((float) $record?->balance_due, 'PHP'))
                    ->rules([
                        'decimal:0,2',
                        fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $settled = bcadd(bcadd(number_format((float) $value, 2, '.', ''), number_format((float) $get('tax_withheld'), 2, '.', ''), 2), number_format((float) $get('vat_withheld'), 2, '.', ''), 2);

                            if (bccomp($settled, '0', 2) <= 0) {
                                $fail('Enter the amount received, the tax withheld, or both');
                            } elseif (bccomp($settled, (string) $record?->balance_due, 2) > 0) {
                                $fail('Amount received and taxes withheld together must be '.Number::currency((float) $record?->balance_due, 'PHP').' or less, the balance due');
                            }
                        },
                    ])
                    ->validationMessages(['decimal' => 'Enter the amount with at most 2 decimal places, such as 1250.50']),
                TextInput::make('tax_withheld')
                    ->label('Income tax withheld (optional)')
                    ->helperText('As on the customer\'s 2307, even before it arrives')
                    ->numeric()
                    ->prefix('₱')
                    ->step(0.01)
                    ->minValue(0)
                    ->rules(['decimal:0,2'])
                    ->maxWidth('xs')
                    ->default(fn (?Model $record): string => DocumentTotals::expectedWithholding((string) $record?->balance_due, (string) $record?->total, bcsub((string) $record?->total, (string) $record?->vat, 2), DocumentTotals::customerRates($record?->customer_withholding, (string) $record?->total)['income_tax']))
                    ->validationMessages(['decimal' => 'Enter the income tax withheld with at most 2 decimal places, such as 1000.00']),
                Select::make('tax_atc')
                    ->label('Income tax ATC')
                    ->helperText('Needed when income tax is withheld, as on the customer\'s 2307')
                    ->options(fn (): array => WithholdingCode::query()->where('kind', 'income')->pluck('code', 'code')->all())
                    ->required(fn (Get $get): bool => bccomp(number_format((float) $get('tax_withheld'), 2, '.', ''), '0', 2) > 0)
                    ->maxWidth('xs')
                    ->validationMessages(['required' => 'Select the ATC of the income tax withheld, as on the customer\'s 2307']),
                TextInput::make('vat_withheld')
                    ->label('VAT withheld (optional)')
                    ->helperText('Withheld by a government buyer; claimed on the VAT return')
                    ->numeric()
                    ->prefix('₱')
                    ->step(0.01)
                    ->minValue(0)
                    ->rules(['decimal:0,2'])
                    ->maxWidth('xs')
                    ->default(fn (?Model $record): string => DocumentTotals::expectedWithholding((string) $record?->balance_due, (string) $record?->total, (string) $record?->vatable_sales, DocumentTotals::customerRates($record?->customer_withholding, (string) $record?->total)['vat']))
                    ->validationMessages(['decimal' => 'Enter the VAT withheld with at most 2 decimal places, such as 5000.00']),
                TextInput::make('reference')
                    ->label('Reference (optional)')
                    ->helperText('A check number or bank reference')
                    ->maxLength(50)
                    ->maxWidth('xs'),
            ])
            ->modalSubmitActionLabel('Record payment')
            ->successNotificationTitle(fn (array $data): string => "Receipt {$data['number']} recorded")
            ->failureNotificationTitle(fn (array $data): string => "Receipt {$data['number']} not recorded")
            ->action(function (Model $record, array $data, Action $action): void {
                try {
                    // On a resource, inside DB::transaction(): lock the invoice with lockForUpdate(), check again that it is posted and the amounts fit its balance,
                    // and lower balance_due by the amount plus both taxes withheld, with bcmath. Keep each tax with its ATC for its 2307.
                    // The SAWT reads the income payment and the ATC from each payment.
                    $settled = bcadd(bcadd(number_format((float) $data['amount'], 2, '.', ''), number_format((float) $data['tax_withheld'], 2, '.', ''), 2), number_format((float) $data['vat_withheld'], 2, '.', ''), 2);
                    $record->payments()->create([...$data, 'income_payment' => DocumentTotals::incomePayment($settled, (string) $record->total, (string) $record->vat), 'status' => DocumentStatus::Posted]);
                } catch (UniqueConstraintViolationException) {
                    // The unique index on payments.number caught a serial another user recorded after this form was checked.
                    $action->failureNotificationBody("Receipt {$data['number']} was just recorded by someone else. Check the serial on the receipt you wrote.");
                    $action->sendFailureNotification();
                    $action->halt();
                }

                $action->success();
            });
    }

    /** On the customer's form: who withholds from our invoices, which sets the withholding a collection proposes. */
    public function customerWithholding(): Radio
    {
        return Radio::make('customer_withholding')
            ->label('Withholding')
            ->options([
                'government' => 'Government office',
                'top_withholding_agent' => 'Top withholding agent',
                'none' => 'None',
            ])
            ->descriptions([
                'government' => '1% income tax and 5% VAT',
                'top_withholding_agent' => '1% income tax',
            ])
            ->default('none')
            ->required();
    }

    /** A payment to a supplier: the tax withheld was worked out on their invoice, so the payment settles the net payable. */
    public function supplierPayment(): Action
    {
        return Action::make('recordPayment')
            ->label('Record payment')
            ->visible(fn (?Model $record): bool => $record?->status === DocumentStatus::Posted && bccomp((string) $record?->balance_due, '0', 2) > 0)
            ->modalHeading(fn (?Model $record): string => "Record payment to {$record?->supplier}")
            ->schema([
                DatePicker::make('paid_on')
                    ->label('Payment date')
                    ->default(today())
                    ->maxDate(today())
                    ->required()
                    ->maxWidth('xs'),
                TextInput::make('amount')
                    ->label('Amount paid')
                    ->numeric()
                    ->prefix('₱')
                    ->step(0.01)
                    ->minValue(0.01)
                    ->maxValue(fn (?Model $record): string => (string) $record?->balance_due)
                    ->rules(['decimal:0,2'])
                    ->required()
                    ->maxWidth('xs')
                    ->helperText(fn (?Model $record): string => 'Net payable '.Number::currency((float) $record?->balance_due, 'PHP'))
                    ->validationMessages([
                        'min' => 'Amount paid must be ₱0.01 or more',
                        'max' => fn (?Model $record): string => 'Amount paid must be '.Number::currency((float) $record?->balance_due, 'PHP').' or less, the net payable',
                        'decimal' => 'Enter the amount with at most 2 decimal places, such as 1250.50',
                    ]),
                TextEntry::make('tax_withheld')
                    ->label('Tax withheld')
                    ->state(fn (?Model $record): ?string => $record?->tax_withheld)
                    ->money('PHP'),
                TextInput::make('reference')
                    ->label('Reference (optional)')
                    ->helperText('A check number or bank reference')
                    ->maxLength(50)
                    ->maxWidth('xs'),
            ])
            ->modalSubmitActionLabel('Record payment')
            ->successNotificationTitle(fn (?Model $record): string => "Payment to {$record?->supplier} recorded")
            ->action(function (Model $record, array $data, Action $action): void {
                // On a resource, in DB::transaction(): lock the order with lockForUpdate(), check again that it is posted and the amount fits its balance,
                // create the payment under DocumentNumber::next('PV'), and lower balance_due by the amount.
                $action->success();
            });
    }
}
