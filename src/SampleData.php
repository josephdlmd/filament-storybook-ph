<?php

namespace Josephdlmd\FilamentStorybookPh;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * What the Philippine patterns add to the core sample database (`SampleDatabase::extend()`): TINs, VAT on invoices and
 * orders, the withholding codes, and the tax withheld from payments, with the figures that go with them.
 */
class SampleData
{
    public static function extend(Builder $schema, Connection $db): void
    {
        $schema->table('customers', fn (Blueprint $table) => $table->string('tin')->nullable());

        $schema->table('sales_invoices', function (Blueprint $table): void {
            $table->decimal('vatable_sales', 15, 2)->default(0);
            $table->decimal('vat', 15, 2)->default(0);
            $table->decimal('zero_rated_sales', 15, 2)->default(0);
            $table->decimal('exempt_sales', 15, 2)->default(0);
        });

        $schema->create('withholding_codes', function (Blueprint $table): void {
            $table->string('code')->primary();
            $table->string('description');
            $table->decimal('rate', 5, 2);
            $table->string('kind');
            $table->string('withheld_by');
        });

        // The withholding, worked out once when the supplier's invoice is recorded (business standards).
        $schema->table('purchase_orders', function (Blueprint $table): void {
            $table->decimal('vatable_purchases', 15, 2)->default(0);
            $table->decimal('vat', 15, 2)->default(0);
            $table->string('supplier_tin')->nullable();
            $table->string('withholding_code')->nullable();
            $table->date('invoice_recorded_on')->nullable();
            $table->decimal('tax_withheld', 15, 2)->default(0);
        });

        // As on the customer's 2307: the VAT-exclusive amount withheld on, the income tax's ATC, and when it arrived.
        $schema->table('payments', function (Blueprint $table): void {
            $table->decimal('tax_withheld', 15, 2)->default(0);
            $table->decimal('vat_withheld', 15, 2)->default(0);
            $table->decimal('income_payment', 15, 2)->default(0);
            $table->string('tax_atc')->nullable();
            $table->date('received_2307_on')->nullable();
        });

        foreach (['001232' => ['3058.93', '367.07', '2900.00'], '001234' => ['3003.31', '360.40', '0.00'], '000901' => ['4464.29', '535.71', '0.00'], '000902' => ['795.09', '95.41', '0.00'], '000903' => ['10000.00', '1200.00', '0.00']] as $number => [$vatable, $vat, $exempt]) {
            $db->table('sales_invoices')->where('number', $number)->update(['vatable_sales' => $vatable, 'vat' => $vat, 'exempt_sales' => $exempt]);
        }

        // Only the codes the business standards name; the CPA confirms the rest.
        $db->table('withholding_codes')->insert([
            ['code' => 'WC158', 'description' => 'Goods bought by a top withholding agent', 'rate' => '1.00', 'kind' => 'income', 'withheld_by' => 'us'],
            ['code' => 'WC160', 'description' => 'Services bought by a top withholding agent', 'rate' => '2.00', 'kind' => 'income', 'withheld_by' => 'us'],
            ['code' => 'WV010', 'description' => 'VAT withheld by a government office', 'rate' => '5.00', 'kind' => 'vat', 'withheld_by' => 'customer'],
        ]);

        foreach (['PO-000041' => ['4730.36', '567.64', '234-567-890-000', '2026-09-30', '47.30'], 'PO-000042' => ['4730.36', '567.64', '234-567-890-000', '2026-10-12', '47.30'], 'PO-000043' => ['12500.00', '1500.00', '345-678-901-000', '2026-08-20', '125.00'], 'PO-000039' => ['2232.14', '267.86', '234-567-890-000', '2026-07-15', '22.32']] as $number => [$vatable, $vat, $tin, $recorded, $withheld]) {
            $db->table('purchase_orders')->where('number', $number)->update(['vatable_purchases' => $vatable, 'vat' => $vat, 'supplier_tin' => $tin, 'withholding_code' => 'WC158', 'invoice_recorded_on' => $recorded, 'tax_withheld' => $withheld]);
        }

        // What the customers withheld, for the SAWT: Abra's 2307 for July and Cebu's for October are in; Abra's for October isn't yet.
        // Each payment's amount becomes the cash received, what it settles less the tax withheld.
        foreach (['004516' => ['6298.69', '27.31', '0.00', '2731.00', 'WC158', null], '004530' => ['4955.36', '44.64', '0.00', '4464.00', 'WC158', '2026-08-10'], '004531' => ['3183.51', '30.03', '150.17', '3003.31', 'WC158', '2026-10-20'], '004532' => ['882.55', '7.95', '0.00', '795.09', 'WC158', null]] as $number => [$amount, $tax, $vat, $income, $atc, $received]) {
            $db->table('payments')->where('number', $number)->update(['amount' => $amount, 'tax_withheld' => $tax, 'vat_withheld' => $vat, 'income_payment' => $income, 'tax_atc' => $atc, 'received_2307_on' => $received]);
        }

        foreach (['C-0001' => '234-567-890-000', 'C-0002' => '345-678-901-000', 'C-0003' => '987-654-321-000'] as $code => $tin) {
            $db->table('customers')->where('code', $code)->update(['tin' => $tin]);
        }
    }
}
