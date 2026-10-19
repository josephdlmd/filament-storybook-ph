<?php

namespace Josephdlmd\FilamentStorybookPh\Stories\Documents;

use Josephdlmd\FilamentStorybook\Stories\Documents\SampleDatabase;
use Illuminate\Database\Eloquent\Model;

/**
 * A withholding tax code (ATC) and its rate, kept as rows the business updates as its CPA confirms them, never written
 * into the app (owner's business standards, 2026-10-12). `kind` is income or vat; `withheld_by` says who withholds it:
 * we, from a supplier, or a customer, from us. Copy it into the app with a table and a small resource to keep it.
 */
class WithholdingCode extends Model
{
    protected $connection = 'storybook';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    public $timestamps = false;

    /** The rate as a percentage, to 2 decimals as a string, so arithmetic stays exact (bcmath). */
    protected $casts = ['rate' => 'decimal:2'];

    protected static function booted(): void
    {
        SampleDatabase::boot();
    }
}
