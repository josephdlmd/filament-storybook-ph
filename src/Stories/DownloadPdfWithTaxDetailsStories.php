<?php

namespace Josephdlmd\FilamentStorybookPh\Stories;

use Filament\Actions\Action;
use Filament\Schemas\Components\View;
use Illuminate\Database\Eloquent\Model;
use Josephdlmd\FilamentStorybook\Documents\DocumentStatus;
use Josephdlmd\FilamentStorybook\Stories;
use Josephdlmd\FilamentStorybookPh\Documents\PrintedDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadPdfWithTaxDetailsStories extends Stories
{
    public function title(): string
    {
        return 'Actions/Download PDF with tax details';
    }

    public function description(): string
    {
        return 'What `actions-download-pdf` prints for an app that keeps Philippine books, through this package\'s `PrintedDocument` (call it in place of the core one): the company\'s TIN and VAT registration under its address, and the customer\'s or supplier\'s TIN under theirs; a sales invoice\'s VAT per line and its VATable, VAT, zero-rated and VAT-exempt totals; and the tax withheld on a payment voucher and a payment acknowledgement. Sales invoices and collection receipts are written by hand on BIR pre-printed forms, which are the official copies, so a sales invoice\'s printout is titled "Sales invoice (copy)" and not valid for claim of input tax, and what a customer gets by email for a collection is a payment acknowledgement, not an official receipt. `config/company.php` adds `tin` and `vat`. Business standards from the owner (2026-10-11 and 2026-10-12); BIR RR 7-2024 (VAT shown as a separate item).';
    }

    public function examples(): array
    {
        return [PrintedDocument::class];
    }

    public function records(): array
    {
        return [1 => [
            'status' => DocumentStatus::Posted,
            'number' => '001232',
            'invoice_date' => '2026-10-11',
            'terms' => '30 days',
            'due_date' => '2026-11-10',
            'customer' => ['name' => 'Cebu Diagnostics Laboratory', 'address' => '45 Osmeña Boulevard, Cebu City 6000', 'tin' => '987-654-321-000'],
            'lines' => [
                ['description' => 'Nitrile examination gloves, medium, box of 100', 'vat_class' => 'vatable', 'quantity' => '12', 'unit_price' => '285.5'],
                ['description' => 'Insulin pens, 3 mL, pack of 5', 'vat_class' => 'exempt', 'quantity' => '2', 'unit_price' => '1450'],
            ],
            'vatable_sales' => '3058.93',
            'vat' => '367.07',
            'zero_rated_sales' => '0.00',
            'exempt_sales' => '2900.00',
            'total' => '6326.00',
            'notes' => null,
        ]];
    }

    /** The button: a posted document downloads as a PDF to print or keep. */
    public function default(): Action
    {
        return Action::make('downloadPdf')
            ->label('Download PDF')
            ->color('gray')
            ->visible(fn (?Model $record): bool => $record?->status === DocumentStatus::Posted)
            ->action(fn (Model $record): StreamedResponse => PrintedDocument::download(PrintedDocument::salesInvoice($record)));
    }

    /** The printed copy of a sales invoice, whose official copy is the BIR form it was written on. */
    public function salesInvoiceCopy(): View
    {
        return View::make('filament-storybook::documents.document')
            ->viewData(['document' => [
                'title' => 'Sales invoice (copy)',
                'number_label' => 'No.',
                'number' => '001232',
                'company' => ['name' => 'Sample Medical Trading Corporation', 'address' => '12 Rizal Avenue, Quezon City 1100', 'lines' => ['TIN 123-456-789-000, VAT-registered']],
                'party' => ['label' => 'Sold to', 'name' => 'Cebu Diagnostics Laboratory', 'address' => '45 Osmeña Boulevard, Cebu City 6000', 'lines' => ['TIN 987-654-321-000']],
                'details' => ['Date' => 'Oct 11, 2026', 'Terms' => '30 days', 'Due date' => 'Nov 10, 2026'],
                'columns' => [
                    'description' => ['label' => 'Description'],
                    'quantity' => ['label' => 'Quantity', 'format' => 'quantity'],
                    'unit_price' => ['label' => 'Unit price', 'format' => 'unit price'],
                    'vat' => ['label' => 'VAT'],
                    'amount' => ['label' => 'Amount', 'format' => 'money'],
                ],
                'lines' => [
                    ['description' => 'Nitrile examination gloves, medium, box of 100', 'quantity' => '12', 'unit_price' => '285.50', 'vat' => '12%', 'amount' => '3426.00'],
                    ['description' => 'Insulin pens, 3 mL, pack of 5', 'quantity' => '2', 'unit_price' => '1450.00', 'vat' => 'Exempt', 'amount' => '2900.00'],
                ],
                'totals' => ['VATable sales' => '3058.93', 'VAT (12%)' => '367.07', 'Zero-rated sales' => '0.00', 'VAT-exempt sales' => '2900.00', 'Total amount due' => '6326.00'],
                'signatures' => ['Prepared by', 'Received by'],
                'statement' => 'THIS DOCUMENT IS NOT VALID FOR CLAIM OF INPUT TAX',
            ]]);
    }

    /** A payment voucher: what is paid to a supplier against which orders, the tax withheld, and the signatures that approve it. */
    public function paymentVoucher(): View
    {
        return View::make('filament-storybook::documents.document')
            ->viewData(['document' => [
                'title' => 'Payment voucher',
                'number_label' => 'No.',
                'number' => 'PV-000087',
                'company' => ['name' => 'Sample Medical Trading Corporation', 'address' => '12 Rizal Avenue, Quezon City 1100', 'lines' => ['TIN 123-456-789-000, VAT-registered']],
                'party' => ['label' => 'Paid to', 'name' => 'Benguet Trading', 'address' => '8 Session Road, Baguio City 2600', 'lines' => ['TIN 234-567-890-000']],
                'details' => ['Date' => 'Oct 25, 2026', 'Paid by' => 'Check 0051177'],
                'columns' => [
                    'order' => ['label' => 'Purchase order'],
                    'invoice' => ['label' => 'Supplier\'s invoice'],
                    'amount' => ['label' => 'Amount paid', 'format' => 'money'],
                ],
                'lines' => [
                    ['order' => 'PO-000042', 'invoice' => 'BT-20931', 'amount' => '5250.70'],
                ],
                'totals' => ['Invoice total' => '5298.00', 'Tax withheld (WC158, 1%)' => '47.30', 'Amount paid' => '5250.70'],
                'signatures' => ['Prepared by', 'Approved by', 'Received by'],
            ]]);
    }

    /** What a customer gets by email for a collection: an acknowledgement under the receipt form's serial, not an official receipt. */
    public function paymentAcknowledgement(): View
    {
        return View::make('filament-storybook::documents.document')
            ->viewData(['document' => [
                'title' => 'Payment received (copy)',
                'number_label' => 'Receipt No.',
                'number' => '000387',
                'company' => ['name' => 'Sample Medical Trading Corporation', 'address' => '12 Rizal Avenue, Quezon City 1100', 'lines' => ['TIN 123-456-789-000, VAT-registered']],
                'party' => ['label' => 'Received from', 'name' => 'Cebu Diagnostics Laboratory', 'address' => '45 Osmeña Boulevard, Cebu City 6000', 'lines' => ['TIN 987-654-321-000']],
                'details' => ['Date received' => 'Nov 8, 2026', 'Reference' => 'Check 0047731'],
                'columns' => [
                    'invoice' => ['label' => 'Invoice'],
                    'amount' => ['label' => 'Amount settled', 'format' => 'money'],
                ],
                'lines' => [
                    ['invoice' => '001232', 'amount' => '6326.00'],
                ],
                'totals' => ['Amount received' => '6298.69', 'Income tax withheld' => '27.31', 'Total settled' => '6326.00'],
                'statement' => 'THIS IS NOT AN OFFICIAL RECEIPT',
            ]]);
    }
}
