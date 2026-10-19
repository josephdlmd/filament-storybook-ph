<?php

namespace Josephdlmd\FilamentStorybookPh\Stories;

use Filament\Actions\ImportAction;
use Josephdlmd\FilamentStorybook\Reports\ReportExporter;
use Josephdlmd\FilamentStorybook\Stories;
use Josephdlmd\FilamentStorybookPh\Stories\Imports\SupplierTaxExporter;
use Josephdlmd\FilamentStorybookPh\Stories\Imports\SupplierTaxImporter;

class SupplierTaxColumnsStories extends Stories
{
    public function title(): string
    {
        return 'Tables/Supplier tax columns';
    }

    public function description(): string
    {
        return 'What `tables-import-and-export` adds for a supplier in an app that keeps Philippine books: its TIN, checked and stored as `forms-tin` does, which a row then matches on, and its default ATC, a related record found by its code (`relationship()` on the import column, dot notation on the export column), refused unless it is a code the business withholds. Copy the importer and exporter from the examples, or add their columns to the core ones. Business standards from the owner (2026-10-10).';
    }

    public function examples(): array
    {
        return [SupplierTaxImporter::class, SupplierTaxExporter::class, ReportExporter::class];
    }

    /** Import with the TIN and the ATC; the example file shows both. */
    public function default(): ImportAction
    {
        return ImportAction::make()
            ->importer(SupplierTaxImporter::class)
            ->maxRows(5000)
            ->color('gray');
    }
}
