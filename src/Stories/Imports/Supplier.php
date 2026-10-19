<?php

namespace Josephdlmd\FilamentStorybookPh\Stories\Imports;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Josephdlmd\FilamentStorybook\Stories\Imports\Supplier as CoreSupplier;
use Josephdlmd\FilamentStorybookPh\Stories\Documents\WithholdingCode;

/** Stands in for the app's own model: the core supplier with its default ATC. */
class Supplier extends CoreSupplier
{
    protected $table = 'suppliers';

    /** The ATC withheld from the supplier by default, changeable on each purchase. */
    public function withholdingCode(): BelongsTo
    {
        return $this->belongsTo(WithholdingCode::class, 'atc');
    }
}
