<?php

namespace Josephdlmd\FilamentStorybookPh\Stories\Reports;

use Illuminate\Database\Eloquent\Builder;
use Josephdlmd\FilamentStorybook\Documents\DocumentStatus;
use Josephdlmd\FilamentStorybook\Stories\Documents\Payment;

/**
 * The tax customers withheld from us (owner's business standards, 2026-10-18): recorded with each payment as on the
 * customer's 2307, and claimed in the quarter it was paid, whether or not the 2307 is in yet. `sawt()` is the quarter's
 * SAWT, one row per customer TIN and ATC; `awaiting()` is the 2307s still to collect. Copy it into the app, next to the report's page.
 */
class CustomerWithholding
{
    /**
     * The quarter's withholding, by the payment date, 2307 or not, one row per customer TIN and ATC, with the
     * income tax and the VAT withheld apart. The quarter is given as "2026-3", as `SupplierWithholding::quarters()` keys it. Read from a subquery, so the totals row and the export add up whole rows.
     * Given a table's query (a filter's `baseQuery()`), it changes that query in place.
     *
     * @return Builder<Payment>
     */
    public static function sawt(string $quarter, ?Builder $payments = null): Builder
    {
        $from = SupplierWithholding::start($quarter);

        $rows = Payment::query()
            ->join('sales_invoices', 'sales_invoices.id', '=', 'payments.sales_invoice_id')
            ->join('customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->where('payments.status', DocumentStatus::Posted)
            ->whereBetween('payments.paid_on', [$from->toDateString(), $from->copy()->addMonths(3)->subDay()->toDateString()])
            ->where(fn (Builder $query): Builder => $query->where('payments.tax_withheld', '>', 0)->orWhere('payments.vat_withheld', '>', 0))
            ->groupBy('customers.tin', 'customers.name', 'payments.tax_atc')
            ->selectRaw('min(payments.id) as id, customers.tin, customers.name as customer, payments.tax_atc, sum(payments.income_payment) as income_payment, sum(payments.tax_withheld) as tax_withheld, sum(payments.vat_withheld) as vat_withheld');

        return ($payments ?? Payment::query())->fromSub($rows, 'payments');
    }

    /**
     * Posted payments with tax withheld whose 2307 hasn't arrived, oldest first: the certificates to collect from customers.
     *
     * @return Builder<Payment>
     */
    public static function awaiting(): Builder
    {
        return Payment::query()
            ->where('status', DocumentStatus::Posted)
            ->whereNull('received_2307_on')
            ->where(fn (Builder $query): Builder => $query->where('tax_withheld', '>', 0)->orWhere('vat_withheld', '>', 0))
            ->orderBy('paid_on');
    }
}
