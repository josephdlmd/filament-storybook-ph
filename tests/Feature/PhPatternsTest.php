<?php

use Josephdlmd\FilamentStorybook\Livewire\StoryCanvas;
use Josephdlmd\FilamentStorybook\Mcp\ShowStories;
use Josephdlmd\FilamentStorybookPh\Stories\PhoneNumberStories;
use Josephdlmd\FilamentStorybookPh\Stories\TinInputStories;
use Laravel\Mcp\Request;
use Livewire\Livewire;
use Filament\Actions\Testing\TestAction;

it('records a supplier\'s invoice only once it is confirmed as a proper VAT invoice', function (): void {
    Livewire::test(StoryCanvas::class, ['stories' => Josephdlmd\FilamentStorybookPh\Stories\DocumentTotalsWithVatStories::class, 'story' => 'supplierInvoice'])
        ->mountAction(Filament\Actions\Testing\TestAction::make('recordSupplierInvoice')->schemaComponent('story', 'form'))
        ->fillForm(['supplier_invoice_number' => 'BT-20931', 'supplier_invoice_date' => today()->toDateString(), 'total' => '5298.00', 'vat' => '567.64', 'is_vat_invoice' => false])
        ->callMountedAction()
        ->assertHasFormErrors(['is_vat_invoice' => 'accepted']);
});

it('warns, without blocking, when the printed VAT is more than ₱0.05 from 12/112 of the VATable total', function (string $vat, bool $warned): void {
    $canvas = Livewire::test(StoryCanvas::class, ['stories' => Josephdlmd\FilamentStorybookPh\Stories\DocumentTotalsWithVatStories::class, 'story' => 'supplierInvoice'])
        ->mountAction(Filament\Actions\Testing\TestAction::make('recordSupplierInvoice')->schemaComponent('story', 'form'))
        ->fillForm(['supplier_invoice_number' => 'BT-20931', 'supplier_invoice_date' => today()->toDateString(), 'total' => '5298.00', 'vat' => $vat, 'is_vat_invoice' => true]);

    // Only visible components are listed. 12/112 of ₱5,298.00 is ₱567.6429.
    $shown = collect($canvas->instance()->getSchema('mountedActionSchema0')->getFlatComponents())
        ->contains(fn ($component): bool => $component instanceof Filament\Schemas\Components\Callout);

    expect($shown)->toBe($warned);
    $canvas->callMountedAction()->assertHasNoFormErrors();
})->with([
    'as worked out' => ['567.64', false],
    'within ₱0.05' => ['567.69', false],
    'over ₱0.05' => ['576.40', true],
]);

it('withholds at the rate the codes table gives, not one written into the app', function (): void {
    // The sample database lives for the whole test run, so the rate goes back afterwards.
    $code = Josephdlmd\FilamentStorybookPh\Stories\Documents\WithholdingCode::find('WC158');
    $code->update(['rate' => '1.50']);

    try {
        $canvas = Livewire::test(StoryCanvas::class, ['stories' => Josephdlmd\FilamentStorybookPh\Stories\DocumentTotalsWithVatStories::class, 'story' => 'supplierInvoice'])
            ->mountAction(Filament\Actions\Testing\TestAction::make('recordSupplierInvoice')->schemaComponent('story', 'form'))
            ->fillForm(['total' => '5298.00', 'vat' => '567.64']);

        // 1.5% of ₱4,730.36 is ₱70.9554.
        expect($canvas->instance()->getSchema('mountedActionSchema0')->getComponent('tax_withheld')->getState())->toBe('70.96');
    } finally {
        $code->update(['rate' => '1.00']);
    }
});

it('imports a CSV row with the form\'s checks, errors and stored formats, and shows agents the importer to copy', function (): void {
    Illuminate\Support\Facades\Schema::create('suppliers', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('tin')->unique();
        $table->decimal('credit_limit', 15, 2)->nullable();
        $table->string('atc')->nullable();
        $table->timestamps();
    });

    $import = new Josephdlmd\FilamentStorybookPh\Stories\Imports\SupplierTaxImporter(new Filament\Actions\Imports\Models\Import, ['name' => 'name', 'tin' => 'tin', 'credit_limit' => 'credit_limit', 'withholdingCode' => 'atc'], []);

    $import(['name' => 'Abra Medical Supply', 'tin' => '123 456 789 000', 'credit_limit' => '250000.50']);
    // The same TIN, typed another way, updates that supplier.
    $import(['name' => 'Abra Medical Supply Inc.', 'tin' => '123.456.789.000', 'credit_limit' => '250000.50']);

    // The amount reaches the model as typed, never a float (SQLite itself reads decimals back as floats).
    expect(Josephdlmd\FilamentStorybookPh\Stories\Imports\Supplier::query()->get(['name', 'tin'])->toArray())
        ->toBe([['name' => 'Abra Medical Supply Inc.', 'tin' => '123-456-789-000']])
        ->and($import->getData()['credit_limit'])->toBe('250000.50')
        ->and(fn () => $import(['name' => 'Benguet Trading', 'tin' => '123-456-789', 'credit_limit' => '1']))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'Enter the TIN as 12 digits, such as 123-456-789-000')
        // Refused by the form, so refused here too, never stripped down to its digits.
        ->and(fn () => $import(['name' => 'Benguet Trading', 'tin' => '123456789000X', 'credit_limit' => '1']))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'Enter the TIN as 12 digits, such as 123-456-789-000')
        ->and(fn () => $import(['name' => 'Benguet Trading', 'tin' => '987-654-321-000', 'credit_limit' => '1.234']))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'Enter the credit limit with at most 2 decimal places')
        // A related record by its code: found however it is typed, and refused with the house wording when unknown.
        ->and(fn () => $import(['name' => 'Benguet Trading', 'tin' => '987-654-321-000', 'atc' => 'WC999']))
        ->toThrow(Illuminate\Validation\ValidationException::class, 'Enter an ATC we withhold, such as WC158, or leave it empty');

    $import(['name' => 'Benguet Trading', 'tin' => '987-654-321-000', 'atc' => ' wc158 ']);

    expect(Josephdlmd\FilamentStorybookPh\Stories\Imports\Supplier::query()->where('tin', '987-654-321-000')->value('atc'))->toBe('WC158');

    expect((string) (new ShowStories)->handle(new Request(['id' => 'tables-supplier-tax-columns']))->content())
        ->toContain('## SupplierTaxImporter')
        ->toContain('public function resolveRecord(): Supplier');
});

it('works out a document\'s VAT once per class, as 12/112 of the VAT-inclusive total, half up', function (array $lines, array $totals): void {
    expect(Josephdlmd\FilamentStorybookPh\Documents\DocumentTotals::of($lines))->toBe($totals);
})->with([
    'VAT from a VAT-inclusive total' => [[['quantity' => '1', 'unit_price' => '112', 'vat_class' => 'vatable']], ['vatable_sales' => '100.00', 'vat' => '12.00', 'zero_rated_sales' => '0.00', 'exempt_sales' => '0.00', 'total' => '112.00']],
    'once per class, not per line' => [[['quantity' => '1', 'unit_price' => '0.05', 'vat_class' => 'vatable'], ['quantity' => '1', 'unit_price' => '0.05', 'vat_class' => 'vatable']], ['vatable_sales' => '0.09', 'vat' => '0.01', 'zero_rated_sales' => '0.00', 'exempt_sales' => '0.00', 'total' => '0.10']],
    'every class apart' => [[['quantity' => '12', 'unit_price' => '285.5', 'vat_class' => 'vatable'], ['quantity' => '2', 'unit_price' => '1450', 'vat_class' => 'exempt'], ['quantity' => '1', 'unit_price' => '500', 'vat_class' => 'zero_rated']], ['vatable_sales' => '3058.93', 'vat' => '367.07', 'zero_rated_sales' => '500.00', 'exempt_sales' => '2900.00', 'total' => '6826.00']],
    'a credit mirrors its sale' => [[['quantity' => '-1', 'unit_price' => '112', 'vat_class' => 'vatable']], ['vatable_sales' => '-100.00', 'vat' => '-12.00', 'zero_rated_sales' => '0.00', 'exempt_sales' => '0.00', 'total' => '-112.00']],
    'an unfinished line counts as nothing' => [[['quantity' => '', 'unit_price' => '50', 'vat_class' => 'vatable'], ['quantity' => '1', 'unit_price' => '50']], ['vatable_sales' => '0.00', 'vat' => '0.00', 'zero_rated_sales' => '0.00', 'exempt_sales' => '0.00', 'total' => '0.00']],
]);

it('withholds from a supplier\'s invoice once, the rate on its total less VAT, and writes amounts as on a receipt', function (): void {
    $totals = Josephdlmd\FilamentStorybookPh\Documents\DocumentTotals::class;

    $code = fn (string $code) => Josephdlmd\FilamentStorybookPh\Stories\Documents\WithholdingCode::find($code);

    expect($totals::withholding('5298.00', '567.64', $code('WC158')))->toBe('47.30')
        ->and($totals::withholding('1120.00', '120.00', $code('WC160')))->toBe('20.00')
        ->and($totals::withholding('100.50', '0.00', $code('WC158')))->toBe('1.01')
        ->and($totals::withholding('5298.00', '567.64', null))->toBe('0.00')
        ->and($totals::inWords('6266.41'))->toBe('Six thousand two hundred sixty-six pesos and 41/100')
        ->and($totals::inWords('1'))->toBe('One peso and 00/100')
        // ₱6,326.00 with ₱2,900.00 exempt: the VAT is 12/112 of the other ₱3,426.00 alone.
        ->and($totals::vatGap(bcsub('6326.00', '2900.00', 2), '367.07'))->toBe('0.0014');
});

it('records a supplier\'s invoice as printed, withholding at the supplier\'s code once', function (): void {
    $canvas = Livewire::test(StoryCanvas::class, ['stories' => Josephdlmd\FilamentStorybookPh\Stories\DocumentTotalsWithVatStories::class, 'story' => 'supplierInvoice'])
        ->mountAction(Filament\Actions\Testing\TestAction::make('recordSupplierInvoice')->schemaComponent('story', 'form'))
        ->assertSchemaStateSet(['withholding_code' => 'WC158', 'vat' => null], 'mountedActionSchema0')
        ->fillForm(['supplier_invoice_number' => 'BT-20931', 'supplier_invoice_date' => today()->toDateString(), 'total' => '5298.00', 'vat' => '567.64', 'is_vat_invoice' => true]);
    $schema = $canvas->instance()->getSchema('mountedActionSchema0');

    expect($schema->getComponent('tax_withheld')->getState())->toBe('47.30')
        ->and($schema->getComponent('net_payable')->getState())->toBe('5250.70');

    $canvas->callMountedAction()->assertHasNoFormErrors()->assertNotified('Supplier\'s invoice for order PO-000042 recorded');
});

it('checks a payment against the balance due, with the tax withheld', function (array $data, ?string $error): void {
    $canvas = Livewire::test(StoryCanvas::class, ['stories' => Josephdlmd\FilamentStorybookPh\Stories\PaymentWithWithholdingStories::class, 'story' => 'default'])
        ->callAction(Filament\Actions\Testing\TestAction::make('recordPayment')->schemaComponent('story', 'form'), ['paid_on' => today()->toDateString(), 'number' => '004518', 'tax_atc' => 'WC158', ...$data]);

    $error === null ? $canvas->assertHasNoFormErrors() : $canvas->assertHasFormErrors([in_array(array_key_first($data), ['number', 'tax_atc'], true) ? array_key_first($data) : 'amount' => $error]);
})->with([
    'a receipt serial already recorded' => [['number' => '004516', 'amount' => '1000'], 'Enter the serial of the receipt you wrote; receipt 004516 is already recorded'],
    'part of the balance' => [['amount' => '50000', 'tax_withheld' => '500', 'vat_withheld' => '2500'], null],
    'the whole balance, net of both taxes' => [['amount' => '106000.00', 'tax_withheld' => '1000.00', 'vat_withheld' => '5000.00'], null],
    'only the taxes, the cash paid earlier' => [['amount' => '0', 'tax_withheld' => '1000', 'vat_withheld' => '5000'], null],
    'more than the balance' => [['amount' => '106000.00', 'tax_withheld' => '1000.00', 'vat_withheld' => '5000.01'], 'Amount received and taxes withheld together must be ₱112,000.00 or less, the balance due'],
    'nothing' => [['amount' => '0', 'tax_withheld' => '', 'vat_withheld' => ''], 'Enter the amount received, the tax withheld, or both'],
    'income tax withheld with no ATC' => [['tax_atc' => null, 'amount' => '50000', 'tax_withheld' => '500', 'vat_withheld' => '2500'], 'Select the ATC of the income tax withheld, as on the customer\'s 2307'],
]);

it('keeps the income payment and ATC with each payment, for the SAWT', function (): void {
    Livewire::test(StoryCanvas::class, ['stories' => Josephdlmd\FilamentStorybookPh\Stories\PaymentWithWithholdingStories::class, 'story' => 'default'])
        ->callAction(Filament\Actions\Testing\TestAction::make('recordPayment')->schemaComponent('story', 'form'), ['paid_on' => today()->toDateString(), 'number' => '004519', 'amount' => '53000.00', 'tax_withheld' => '500.00', 'vat_withheld' => '2500.00', 'tax_atc' => 'WC158'])
        ->assertHasNoFormErrors();

    // Half the invoice settled: half its ₱100,000 VAT-exclusive amount.
    expect(Josephdlmd\FilamentStorybook\Stories\Documents\Payment::query()->where('number', '004519')->first(['income_payment', 'tax_atc'])->toArray())
        ->toEqual(['income_payment' => '50000.00', 'tax_atc' => 'WC158']);
});

it('proposes what a government office withholds: 1% income tax on the amount less VAT and 5% VAT on the VATable sales, and the cash expected', function (): void {
    Livewire::test(StoryCanvas::class, ['stories' => Josephdlmd\FilamentStorybookPh\Stories\PaymentWithWithholdingStories::class, 'story' => 'default'])
        ->mountAction(Filament\Actions\Testing\TestAction::make('recordPayment')->schemaComponent('story', 'form'))
        ->assertSchemaStateSet(['amount' => '106000.00', 'tax_withheld' => '1000.00', 'vat_withheld' => '5000.00'], 'mountedActionSchema0');

    expect(Josephdlmd\FilamentStorybookPh\Documents\DocumentTotals::expectedWithholding('2363.71', '3363.71', '3003.31', '0.01'))->toBe('21.10')
        // ₱3,426 VATable (₱3,058.93 before VAT) and ₱2,900 exempt: VAT is withheld on the VATable sales alone.
        ->and(Josephdlmd\FilamentStorybookPh\Documents\DocumentTotals::expectedWithholding('6326.00', '6326.00', '3058.93', '0.05'))->toBe('152.95');
});

it('sets what a customer withholds by type, with no income tax on a government purchase of ₱10,000 or less', function (?string $type, string $total, array $rates): void {
    expect(Josephdlmd\FilamentStorybookPh\Documents\DocumentTotals::customerRates($type, $total))->toBe($rates);
})->with([
    'government, over ₱10,000' => ['government', '10000.01', ['income_tax' => '0.01', 'vat' => '0.05']],
    'government, exactly ₱10,000' => ['government', '10000.00', ['income_tax' => '0', 'vat' => '0.05']],
    'government, a small purchase' => ['government', '2500.00', ['income_tax' => '0', 'vat' => '0.05']],
    'a top withholding agent' => ['top_withholding_agent', '2500.00', ['income_tax' => '0.01', 'vat' => '0']],
    'no withholding' => ['none', '112000.00', ['income_tax' => '0', 'vat' => '0']],
    'not set' => [null, '112000.00', ['income_tax' => '0', 'vat' => '0']],
]);

it('offers a top withholding agent\'s codes for a supplier only when the company is one', function (bool $agent, array $codes): void {
    config(['company.top_withholding_agent' => $agent]);

    $canvas = Livewire::test(StoryCanvas::class, ['stories' => Josephdlmd\FilamentStorybookPh\Stories\DocumentTotalsWithVatStories::class, 'story' => 'supplierInvoice'])
        ->mountAction(Filament\Actions\Testing\TestAction::make('recordSupplierInvoice')->schemaComponent('story', 'form'));

    expect(array_keys($canvas->instance()->getSchema('mountedActionSchema0')->getComponent('withholding_code')->getOptions()))->toBe($codes);
})->with([
    'a top withholding agent' => [true, ['WC158', 'WC160', 'none']],
    'any other company' => [false, ['none']],
]);

it('downloads a posted document as a tagged A4 PDF in the shared layout, an invoice marked as a copy', function (): void {
    Spatie\LaravelPdf\Facades\Pdf::fake();

    Livewire::test(StoryCanvas::class, ['stories' => Josephdlmd\FilamentStorybookPh\Stories\DownloadPdfWithTaxDetailsStories::class, 'story' => 'default'])
        ->callAction(Filament\Actions\Testing\TestAction::make('downloadPdf')->schemaComponent('story', 'form'))
        ->assertFileDownloaded('Sales invoice (copy) 001232.pdf');

    Spatie\LaravelPdf\Facades\Pdf::assertViewIs('filament-storybook::documents.print');
    Spatie\LaravelPdf\Facades\Pdf::assertSee(['Sales invoice (copy)', '001232', 'Cebu Diagnostics Laboratory', '₱3,426.00', '₱367.07', '₱6,326.00', 'NOT VALID FOR CLAIM OF INPUT TAX', '<html lang="en">', '<title>Sales invoice (copy) 001232</title>', '<th scope="col"']);
    $pdf = (fn (): Spatie\LaravelPdf\PdfBuilder => $this->generatedPdfs[0])->call(Spatie\LaravelPdf\Facades\Pdf::getFacadeRoot());

    expect($pdf->tagged)->toBeTrue()
        ->and((fn (): ?string => $this->format)->call($pdf))->toBe('a4');
});

it('acknowledges a collection under its receipt\'s serial, not as an official receipt', function (): void {
    $payment = (new class extends Illuminate\Database\Eloquent\Model {})->forceFill(['number' => '000387', 'paid_on' => '2026-11-08', 'amount' => '6298.69', 'tax_withheld' => '27.31', 'vat_withheld' => '0.00', 'reference' => 'Check 0047731']);

    $document = Josephdlmd\FilamentStorybookPh\Documents\PrintedDocument::paymentAcknowledgement($payment);

    expect($document['totals'])->toBe(['Amount received' => '6298.69', 'Income tax withheld' => '27.31', 'Total settled' => '6326.00'])
        ->and($document['statement'])->toBe('THIS IS NOT AN OFFICIAL RECEIPT');
});

it('claims customer withholding in the quarter it was paid, 2307 or not, a quarter at a time', function (): void {
    $CustomerWithholding = Josephdlmd\FilamentStorybookPh\Stories\Reports\CustomerWithholding::class;
    $sawt = fn (string $quarter): array => $CustomerWithholding::sawt($quarter)->orderBy('customer')->get()
        ->map(fn ($row): array => [$row->tin, $row->customer, $row->tax_atc, number_format((float) $row->income_payment, 2, '.', ''), number_format((float) $row->tax_withheld, 2, '.', ''), number_format((float) $row->vat_withheld, 2, '.', '')])->all();

    // Still waiting, oldest first: Abra's October payment and Cebu's November one.
    expect($CustomerWithholding::awaiting()->pluck('number')->all())->toBe(['004532', '004516'])
        // October to December: Abra's and both of Cebu's, 2307 or not; the void payment is left out.
        ->and($sawt('2026-4'))->toBe([['234-567-890-000', 'Abra Medical Supply', 'WC158', '795.09', '7.95', '0.00'], ['987-654-321-000', 'Cebu Diagnostics Laboratory', 'WC158', '5734.31', '57.34', '150.17']])
        ->and($sawt('2026-3'))->toBe([['234-567-890-000', 'Abra Medical Supply', 'WC158', '4464.00', '44.64', '0.00']])
        ->and($sawt('2026-1'))->toBe([]);

    $this->travelTo('2026-10-12');

    Livewire::test(StoryCanvas::class, ['stories' => Josephdlmd\FilamentStorybookPh\Stories\CustomerWithholdingStories::class, 'story' => 'default'])
        ->call('loadTable')
        // It opens on the quarter just ended, July to September.
        ->assertSee(['Jul–Sep 2026', 'Abra Medical Supply', '₱44.64'])
        ->assertDontSee('Cebu Diagnostics Laboratory')
        ->filterTable('quarter', ['quarter' => '2026-4'])
        ->assertSee(['Cebu Diagnostics Laboratory', '₱150.17', 'Abra Medical Supply', '₱7.95']);
});

it('marks a customer\'s 2307 received on a date no later than today', function (): void {
    $this->travelTo('2026-10-12');
    $action = Filament\Actions\Testing\TestAction::make('markForm2307Received')->schemaComponent('story', 'form');

    Livewire::test(StoryCanvas::class, ['stories' => Josephdlmd\FilamentStorybookPh\Stories\Form2307ReceivedStories::class, 'story' => 'default'])
        ->callAction($action, ['received_2307_on' => null])
        ->assertHasFormErrors(['received_2307_on' => 'required'])
        ->callAction($action, ['received_2307_on' => '2026-10-13'])
        ->assertHasFormErrors(['received_2307_on' => 'before_or_equal'])
        ->callAction($action, ['received_2307_on' => '2026-10-12'])
        ->assertHasNoFormErrors()
        ->assertNotified('2307 for receipt 004516 marked received');
});

it('groups the tax withheld from each supplier by quarter and month, as Form 2307 lists it', function (): void {
    $rows = Josephdlmd\FilamentStorybookPh\Stories\Reports\SupplierWithholding::quarterQuery(Josephdlmd\FilamentStorybookPh\Stories\Documents\PurchaseOrder::query(), '2026-3')
        ->where('supplier', 'Benguet Trading')
        ->get();

    // PO-000039 in July and PO-000041 in September; PO-000042, recorded in October, is the next quarter's.
    $document = Josephdlmd\FilamentStorybookPh\Documents\PrintedDocument::form2307Details('Benguet Trading', '234-567-890-000', '8 Session Road, Baguio City 2600', Josephdlmd\FilamentStorybookPh\Stories\Reports\SupplierWithholding::start('2026-3'), $rows);

    expect($document['number'])->toBe('Jul 1, 2026 to Sep 30, 2026')
        ->and($document['lines'])->toBe([['atc' => 'WC158', 'month_1' => '2232.14', 'month_2' => '0.00', 'month_3' => '4730.36', 'income_payment' => '6962.50', 'tax_withheld' => '69.62']])
        ->and($document['totals'])->toBe(['Total income payments' => '6962.50', 'Total tax withheld' => '69.62']);
});

it('reports the quarter just ended and downloads each supplier\'s 2307 details, the report and the QAP', function (): void {
    $this->travelTo('2026-10-12');
    Spatie\LaravelPdf\Facades\Pdf::fake();

    $report = Livewire::test(StoryCanvas::class, ['stories' => Josephdlmd\FilamentStorybookPh\Stories\SupplierWithholdingStories::class, 'story' => 'default'])
        ->call('loadTable')
        ->assertSee(['Benguet Trading', 'Luzon Medical Gases', '₱19,462.50', '₱194.62'])
        ->assertActionVisible(Filament\Actions\Testing\TestAction::make('export')->table())
        // Benguet's row is keyed by its first order, PO-000041.
        ->callAction(Filament\Actions\Testing\TestAction::make('download2307')->table(1))
        ->assertFileDownloaded('Form 2307 details Jul 1, 2026 to Sep 30, 2026.pdf');

    Spatie\LaravelPdf\Facades\Pdf::assertSee(['Form 2307 details', 'Benguet Trading', '234-567-890-000', '₱2,232.14', '₱4,730.36', '₱69.62', 'not the certificate']);

    // A fresh fake, since assertSee looks in every PDF made so far.
    Spatie\LaravelPdf\Facades\Pdf::fake();
    $report->callAction(Filament\Actions\Testing\TestAction::make('downloadPdf')->table())
        ->assertFileDownloaded('Tax withheld from suppliers Oct 12, 2026.pdf');

    Spatie\LaravelPdf\Facades\Pdf::assertSee(['Tax withheld from suppliers', 'Jul–Sep 2026', 'Luzon Medical Gases', '₱19,462.50']);

    expect(collect(Josephdlmd\FilamentStorybookPh\Stories\Reports\QapExporter::getColumns())->map->getName()->all())
        ->toBe(['supplier_tin', 'supplier', 'withholding_code', 'withholdingCode.rate', 'income_payment', 'tax_withheld']);
});

it('names a report\'s export after the report and the day, as its PDF is', function (string $exporter, string $name): void {
    $export = new Filament\Actions\Exports\Models\Export;
    $export->created_at = Illuminate\Support\Carbon::parse('2026-10-13 09:00');

    expect((new $exporter($export, [], []))->getFileName($export))->toBe($name);
})->with([
    'QAP' => [Josephdlmd\FilamentStorybookPh\Stories\Reports\QapExporter::class, 'QAP Oct 13, 2026'],
    'SAWT' => [Josephdlmd\FilamentStorybookPh\Stories\Reports\SawtExporter::class, 'SAWT Oct 13, 2026'],
]);

it('lists a supplier once per ATC in a quarter, even when its address was corrected', function (): void {
    Josephdlmd\FilamentStorybookPh\Stories\Documents\PurchaseOrder::query()->insert([
        'number' => 'PO-000044', 'status' => 'posted', 'supplier' => 'Benguet Trading', 'order_date' => '2026-08-01', 'total' => '1120.00', 'vat' => '120.00',
        'supplier_tin' => '234-567-890-000', 'supplier_address' => '10 Session Road, Baguio City 2600', 'withholding_code' => 'WC158', 'invoice_recorded_on' => '2026-08-03', 'tax_withheld' => '10.00',
    ]);

    $rows = Josephdlmd\FilamentStorybookPh\Stories\Reports\SupplierWithholding::quarterQuery(Josephdlmd\FilamentStorybookPh\Stories\Documents\PurchaseOrder::query(), '2026-3')
        ->where('supplier_tin', '234-567-890-000')
        ->get();

    expect($rows)->toHaveCount(1)
        ->and((float) $rows->sole()->income_payment)->toBe(7962.5);
});

it('accepts a TIN typed any reasonable way and stores one format', function (string $typed, ?string $stored): void {
    $canvas = Livewire::test(StoryCanvas::class, ['stories' => TinInputStories::class, 'story' => 'required'])->set('data.tin', $typed);

    $stored !== null
        ? expect($canvas->instance()->form->getState())->toBe(['tin' => $stored])
        : expect(fn () => $canvas->instance()->form->getState())->toThrow(Illuminate\Validation\ValidationException::class, 'Enter a TIN in the correct format');
})->with([
    'dashes, head office' => ['123-456-789-000', '123-456-789-000'],
    'spaces, branch' => ['123 456 789 002', '123-456-789-002'],
    'digits only' => ['123456789000', '123-456-789-000'],
    'dots and stray spaces' => [' 123.456.789.000 ', '123-456-789-000'],
    'missing branch code' => ['123-456-789', null],
    'letters' => ['ABC-456-789-000', null],
]);

it('accepts a Philippine phone number typed any reasonable way and stores it as +63', function (string $typed, ?string $stored): void {
    $canvas = Livewire::test(StoryCanvas::class, ['stories' => PhoneNumberStories::class, 'story' => 'default'])->set('data.phone', $typed);

    $stored !== null
        ? expect($canvas->instance()->form->getState())->toBe(['phone' => $stored])
        : expect(fn () => $canvas->instance()->form->getState())->toThrow(Illuminate\Validation\ValidationException::class, 'Enter a phone number, like 0917 123 4567');
})->with([
    'mobile' => ['0917 123 4567', '+63 917 123 4567'],
    'mobile, +63 and dashes' => ['+63 917-123-4567', '+63 917 123 4567'],
    'mobile, +63 and the 0' => ['+63 0917 123 4567', '+63 917 123 4567'],
    'Metro Manila landline' => ['(02) 8123 4567', '+63 2 8123 4567'],
    'provincial landline' => ['(032) 123-4567', '+63 32 123 4567'],
    'area code 63' => ['(063) 221 1234', '+63 63 221 1234'],
    'too short' => ['0917 123 456', null],
    'foreign' => ['+1 415 555 0100', null],
]);

it('checks a TIN in its stored form, so unique() catches one typed without dashes', function (): void {
    $tin = (new Josephdlmd\FilamentStorybookPh\Stories\TinInputStories)->required();

    expect($tin->mutateStateForValidation('123456789000'))->toBe('123-456-789-000')
        ->and($tin->mutateStateForValidation(' 123.456 789-000 '))->toBe('123-456-789-000')
        // Not a TIN: left as typed, for the format error.
        ->and($tin->mutateStateForValidation('123-456-789'))->toBe('123-456-789');
});


it('passes every check on the Philippine patterns, layered on the core ones', function (): void {
    $ids = collect(app(Josephdlmd\FilamentStorybook\StoryRegistry::class)->all())->filter(fn ($stories): bool => str_starts_with($stories::class, 'Josephdlmd\\FilamentStorybookPh\\'))->keys()->all();

    expect(testStoriesWithTheme(['ids' => $ids]))->toContain(' 0 failing, 0 accessibility issues');
});

it('gives agents the Philippine rules after the core ones', function (): void {
    expect((string) (new Josephdlmd\FilamentStorybook\Mcp\ShowInstructions)->handle(new Laravel\Mcp\Request)->content())
        ->toContain('# UI rules and their basis')
        ->toContain('# Philippine accounting rules');
});
