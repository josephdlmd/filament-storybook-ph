<?php

namespace Josephdlmd\FilamentStorybookPh\Stories\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Josephdlmd\FilamentStorybook\Documents\DocumentStatus;

/**
 * The tax we withheld from suppliers in a quarter, one row per supplier and ATC, with the income payments of each month
 * of the quarter, as BIR Form 2307 lists them (owner's business standards: one 2307 per supplier per quarter). A
 * withholding falls in the month its supplier's invoice was recorded, when it became due (RR 4-2024). Worked out in the
 * database, so the report's totals row can add it. Copy it into the app, next to the report's page.
 */
class SupplierWithholding
{
    /**
     * The quarters to choose from, newest first: "2026-3" => "Jul–Sep 2026".
     *
     * @return array<string, string>
     */
    public static function quarters(int $count = 8): array
    {
        return collect(range(0, $count - 1))
            ->map(fn (int $back): Carbon => today()->firstOfQuarter()->subQuarters($back))
            ->mapWithKeys(fn (Carbon $start): array => [
                "{$start->year}-{$start->quarter}" => $start->format('M').'–'.$start->copy()->addMonths(2)->format('M Y'),
            ])
            ->all();
    }

    /** The quarter just ended: the one whose 2307s are due now. */
    public static function lastQuarter(): string
    {
        $start = today()->firstOfQuarter()->subQuarter();

        return "{$start->year}-{$start->quarter}";
    }

    /** The first day of a quarter given as "2026-3". */
    public static function start(string $quarter): Carbon
    {
        [$year, $number] = array_map('intval', explode('-', $quarter));

        return Carbon::create($year, ($number - 1) * 3 + 1, 1)->startOfDay();
    }

    /**
     * The purchase orders' query, reading from a subquery with one row per supplier and ATC for the quarter: `month_1`
     * to `month_3` (income payments: each invoice's total less VAT), `income_payment` and `tax_withheld`.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $orders
     */
    public static function quarterQuery(Builder $orders, string $quarter): Builder
    {
        $model = $orders->getModel();
        $month = fn (int $i): array => [static::start($quarter)->addMonths($i)->toDateString(), static::start($quarter)->addMonths($i)->endOfMonth()->toDateString()];

        // Each row is keyed by its first order. Filament finds a row by its key as a string, which SQLite compares equal to
        // an aggregate only once the aggregate is cast to a number.
        $key = $model->getConnection()->getDriverName() === 'sqlite' ? 'cast(min(id) as integer)' : 'min(id)';

        $grouped = $model->newQuery()
            // One row per TIN and ATC, even when the supplier's name or address was corrected during the quarter.
            ->selectRaw("{$key} as id, max(supplier) as supplier, supplier_tin, max(supplier_address) as supplier_address, withholding_code")
            ->selectRaw('sum(case when invoice_recorded_on between ? and ? then total - vat else 0 end) as month_1', $month(0))
            ->selectRaw('sum(case when invoice_recorded_on between ? and ? then total - vat else 0 end) as month_2', $month(1))
            ->selectRaw('sum(case when invoice_recorded_on between ? and ? then total - vat else 0 end) as month_3', $month(2))
            ->selectRaw('sum(total - vat) as income_payment, sum(tax_withheld) as tax_withheld')
            ->where('status', DocumentStatus::Posted)
            ->whereNotNull('withholding_code')
            ->whereBetween('invoice_recorded_on', [$month(0)[0], $month(2)[1]])
            ->groupBy('supplier_tin', 'withholding_code');

        return $orders->fromSub($grouped, $model->getTable());
    }
}
