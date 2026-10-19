# Filament Storybook PH

Philippine accounting patterns for [filament-storybook](https://github.com/josephdlmd/filament-storybook): VAT, withholding, TINs and BIR forms, layered on the core patterns. An app that keeps Philippine books requires it alongside the core package; the starter and `storybook:install` stay core-only.

```bash
composer config repositories.filament-storybook-ph vcs https://github.com/josephdlmd/filament-storybook-ph
composer require josephdlmd/filament-storybook-ph
```

## What it adds

| Pattern | Builds on | What it adds |
|---|---|---|
| `forms-document-totals-with-vat` | `forms-document-totals` | A VAT class per line; VATable, VAT, zero-rated and exempt totals; recording a supplier's invoice as printed, with its withholding |
| `actions-record-payment-with-withholding` | `actions-record-payment` | The income tax and VAT a customer withholds, with the ATC; the customer's withholding type; a supplier payment's net payable |
| `actions-download-pdf-with-tax-details` | `actions-download-pdf` | TINs and VAT registration on every document, VAT on a sales invoice, BIR's wording on copies |
| `reports-sales-with-vat`, `reports-purchases-and-payables-with-vat`, `reports-collections-with-withholding` | the core reports | VAT and withholding columns and exporters |
| `forms-tax-registration` | `forms-settings-page` | The company's TIN, VAT registration and top-withholding-agent status |
| `tables-supplier-tax-columns` | `tables-import-and-export` | A supplier's TIN and default ATC in the import and export |
| `forms-tin`, `forms-philippine-phone-number` | `forms-contact-details` | The TIN and +63 phone formats |
| `reports-tax-withheld-by-customers`, `reports-tax-withheld-from-suppliers`, `actions-mark-2307-received`, `records-paper-receipt` | | The SAWT, the 2307 details and the QAP, a 2307 received, and the BIR paper receipt |

Its classes extend the core ones: `DocumentTotals` (VAT, withholding, amounts in words) and `PrintedDocument` (TINs, VAT, the 2307). Call them from the package. The sample data adds its columns through `SampleDatabase::extend()`, and its rules (`docs/rules.md`) reach agents through `StoryRegistry::addRules()`.

## Changing it

The same rules as the core package (its `AGENTS.md`): a UI rule needs a current source, a business fact comes from the owner and goes in `docs/rules.md`, and nothing real (company names, TINs, client data) goes in this public repo. Run `vendor/bin/pest`; it runs `storybook-test` on every pattern here. A core release that breaks this package ships with a matching release of it.
