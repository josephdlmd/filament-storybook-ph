<?php

namespace Josephdlmd\FilamentStorybookPh\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Josephdlmd\FilamentStorybook\Documents\PrintedDocument as CorePrintedDocument;

/**
 * The core printed documents with what Philippine books add: the company's TIN and VAT registration, the customer's or
 * supplier's TIN, a sales invoice's VAT, the tax withheld from a payment, BIR's wording on copies, and the details of a
 * supplier's BIR Form 2307. Call it from the package in place of the core one.
 */
class PrintedDocument extends CorePrintedDocument
{
    public static function company(): array
    {
        $company = parent::company();
        $company['lines'][] = 'TIN '.config('company.tin').', '.config('company.vat');

        return $company;
    }

    /** A sales invoice's copy: the official invoice is the BIR pre-printed form it was written on, so the copy says so. */
    public static function salesInvoice(Model $invoice): array
    {
        $document = parent::salesInvoice($invoice);
        $document['party']['lines'] = static::tin(data_get($invoice, 'customer.tin'));
        $document['columns'] = [...array_diff_key($document['columns'], ['amount' => true]), 'vat' => ['label' => 'VAT'], 'amount' => $document['columns']['amount']];
        $document['lines'] = collect($document['lines'])->values()->map(fn (array $line, int $index): array => [...$line, 'vat' => DocumentTotals::VAT_CLASSES[data_get($invoice, "lines.{$index}.vat_class")] ?? null])->all();
        // The stored totals, worked out with DocumentTotals when the invoice was saved.
        $document['totals'] = [
            'VATable sales' => $invoice->vatable_sales,
            'VAT (12%)' => $invoice->vat,
            'Zero-rated sales' => $invoice->zero_rated_sales,
            'VAT-exempt sales' => $invoice->exempt_sales,
            'Total amount due' => $invoice->total,
        ];
        $document['statement'] = 'THIS DOCUMENT IS NOT VALID FOR CLAIM OF INPUT TAX';

        return $document;
    }

    public static function statementOfAccount(Model $customer, iterable $invoices, ?Carbon $asOf = null): array
    {
        $document = parent::statementOfAccount($customer, $invoices, $asOf);
        $document['party']['lines'] = static::tin($customer->tin);

        return $document;
    }

    public static function supplierStatement(string|Model $supplier, iterable $orders, ?Carbon $asOf = null): array
    {
        $document = parent::supplierStatement($supplier, $orders, $asOf);
        $document['party']['lines'] = is_string($supplier) ? [] : static::tin($supplier->tin);

        return $document;
    }

    /** A collection's acknowledgement, with what the customer withheld; the official receipt stays the BIR form. */
    public static function paymentAcknowledgement(Model $payment): array
    {
        $document = parent::paymentAcknowledgement($payment);
        $settled = bcadd(bcadd((string) $payment->amount, (string) ($payment->tax_withheld ?? '0'), 2), (string) ($payment->vat_withheld ?? '0'), 2);
        $document['party']['lines'] = static::tin(data_get($payment, 'salesInvoice.customer.tin'));
        $document['lines'] = [['invoice' => data_get($payment, 'salesInvoice.number'), 'amount' => $settled]];
        // Withholding shows only when there is some.
        $document['totals'] = array_filter([
            'Amount received' => $payment->amount,
            'Income tax withheld' => $payment->tax_withheld,
            'VAT withheld' => $payment->vat_withheld,
        ], fn (mixed $amount): bool => (float) $amount !== 0.0) + ['Total settled' => $settled];
        $document['statement'] = 'THIS IS NOT AN OFFICIAL RECEIPT';

        return $document;
    }

    /**
     * What a supplier's BIR Form 2307 for one quarter says, for staff to copy onto BIR's form, which stays the
     * certificate the payor signs: the payee, the period, and for each ATC the income payments of each month of the
     * quarter, their total and the tax withheld (owner's business standards: one 2307 per supplier per quarter).
     *
     * @param  iterable<Model|array<string, mixed>>  $rows  One per ATC, as `SupplierWithholding::quarterQuery()` gives them.
     * @return array<string, mixed>
     */
    public static function form2307Details(string $payee, ?string $tin, ?string $address, Carbon $quarterStart, iterable $rows): array
    {
        // Database sums can come back as floats; each is turned back into its exact 2-decimal string first.
        $amount = fn (mixed $value): string => number_format((float) $value, 2, '.', '');
        $lines = collect($rows)->map(fn (mixed $row): array => [
            'atc' => data_get($row, 'withholding_code'),
            'month_1' => $amount(data_get($row, 'month_1')),
            'month_2' => $amount(data_get($row, 'month_2')),
            'month_3' => $amount(data_get($row, 'month_3')),
            'income_payment' => $amount(data_get($row, 'income_payment')),
            'tax_withheld' => $amount(data_get($row, 'tax_withheld')),
        ]);
        $sum = fn (string $key): string => $lines->reduce(fn (string $sum, array $line): string => bcadd($sum, $line[$key], 2), '0.00');

        return [
            'title' => 'Form 2307 details',
            'number_label' => 'Period',
            'number' => static::period($quarterStart->toDateString(), $quarterStart->copy()->addMonths(2)->endOfMonth()->toDateString()),
            'company' => static::company(),
            'party' => ['label' => 'Payee', 'name' => $payee, 'address' => $address, 'lines' => static::tin($tin)],
            'details' => [],
            'columns' => [
                'atc' => ['label' => 'ATC'],
                'month_1' => ['label' => '1st month', 'format' => 'money'],
                'month_2' => ['label' => '2nd month', 'format' => 'money'],
                'month_3' => ['label' => '3rd month', 'format' => 'money'],
                'income_payment' => ['label' => 'Total', 'format' => 'money'],
                'tax_withheld' => ['label' => 'Tax withheld', 'format' => 'money'],
            ],
            'lines' => $lines->all(),
            'totals' => ['Total income payments' => $sum('income_payment'), 'Total tax withheld' => $sum('tax_withheld')],
            'notes' => 'For copying onto BIR Form 2307, which the payor signs and gives the payee. This page is not the certificate.',
        ];
    }

    /** @return list<string> */
    protected static function tin(?string $tin): array
    {
        return filled($tin) ? ["TIN {$tin}"] : [];
    }
}
