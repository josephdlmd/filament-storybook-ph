<?php

namespace Josephdlmd\FilamentStorybookPh\Documents;

use Illuminate\Support\Number;
use Josephdlmd\FilamentStorybook\Documents\DocumentTotals as CoreTotals;
use Josephdlmd\FilamentStorybookPh\Stories\Documents\WithholdingCode;

/**
 * The core `DocumentTotals` with Philippine VAT and withholding: each line has a VAT class, unit prices include VAT, and
 * the VAT is 12/112 of the VATable total, worked out once per document. Call it from the package.
 */
class DocumentTotals extends CoreTotals
{
    /** The VAT classes a line can have, keyed as stored. */
    public const VAT_CLASSES = ['vatable' => '12%', 'zero_rated' => 'Zero-rated', 'exempt' => 'Exempt'];

    /**
     * Subtotals per VAT class, the VAT on the VATable total (12/112 of it, since prices include VAT) and the total due.
     *
     * @param  iterable<array<string, mixed>>  $lines  Each with `quantity`, `unit_price` and `vat_class`; incomplete lines count as nothing.
     * @return array{vatable_sales: string, vat: string, zero_rated_sales: string, exempt_sales: string, total: string}
     */
    public static function of(iterable $lines): array
    {
        $sums = array_fill_keys(array_keys(self::VAT_CLASSES), '0.00');

        foreach ($lines as $line) {
            $total = static::lineTotal($line['quantity'] ?? null, $line['unit_price'] ?? null);

            if ($total !== null && isset($sums[$line['vat_class'] ?? null])) {
                $sums[$line['vat_class']] = bcadd($sums[$line['vat_class']], $total, 2);
            }
        }

        $vat = static::round(bcdiv(bcmul($sums['vatable'], '12', 2), '112', 10));

        return [
            'vatable_sales' => bcsub($sums['vatable'], $vat, 2),
            'vat' => $vat,
            'zero_rated_sales' => $sums['zero_rated'],
            'exempt_sales' => $sums['exempt'],
            'total' => bcadd(bcadd($sums['vatable'], $sums['zero_rated'], 2), $sums['exempt'], 2),
        ];
    }

    /**
     * The tax withheld from a supplier's invoice: its code's rate on the invoice's total less VAT, worked out once when
     * the invoice is recorded; none without a code. Payments then settle the net payable, and the supplier's BIR Form
     * 2307 for the quarter includes this figure.
     */
    public static function withholding(string $total, string $vat, ?WithholdingCode $code): string
    {
        return $code === null ? '0.00' : static::round(bcmul(bcsub($total, $vat, 2), bcdiv((string) $code->rate, '100', 4), 6));
    }

    /** How far a supplier's printed VAT is from 12/112 of the VATable part of its total, as a positive amount, to 4 decimals. */
    public static function vatGap(string $vatableTotal, string $vat): string
    {
        return ltrim(bcsub($vat, bcdiv(bcmul($vatableTotal, '12', 4), '112', 4), 4), '-');
    }

    /**
     * The rates a customer withholds from an invoice, by their withholding type: a government office 1% income tax and
     * 5% VAT, except no income tax on a single purchase of ₱10,000 or less (RA 10963); a top withholding agent 1%.
     *
     * @return array{income_tax: string, vat: string}
     */
    public static function customerRates(?string $type, string $total): array
    {
        return match ($type) {
            'government' => ['income_tax' => bccomp($total, '10000', 2) <= 0 ? '0' : '0.01', 'vat' => '0.05'],
            'top_withholding_agent' => ['income_tax' => '0.01', 'vat' => '0'],
            default => ['income_tax' => '0', 'vat' => '0'],
        };
    }

    /**
     * What a customer is expected to withhold from a payment of `$amount` against an invoice: the rate on the share of it
     * that `$base` is of the invoice's total. Income tax is on the VAT-exclusive amount (total less VAT); VAT is on the
     * VATable sales alone, since exempt and zero-rated sales carry no VAT. A proposal only; the user records what the
     * customer's BIR Form 2307 shows.
     */
    public static function expectedWithholding(string $amount, string $total, string $base, string $rate): string
    {
        if (bccomp($total, '0', 2) === 0) {
            return '0.00';
        }

        return static::round(bcdiv(bcmul(bcmul($amount, $base, 4), $rate, 6), $total, 10));
    }

    /** The income a payment settles, VAT-exclusive: what its income tax is withheld on, as the 2307 and the SAWT show it. */
    public static function incomePayment(string $settled, string $total, string $vat): string
    {
        return static::expectedWithholding($settled, $total, bcsub($total, $vat, 2), '1');
    }

    /** An amount as it is written on a check or a receipt: "Six thousand two hundred sixty-six pesos and 41/100". */
    public static function inWords(string $amount): string
    {
        [$pesos, $centavos] = explode('.', static::round($amount));

        return ucfirst(Number::spell((int) $pesos)).' '.($pesos === '1' ? 'peso' : 'pesos')." and {$centavos}/100";
    }

}
