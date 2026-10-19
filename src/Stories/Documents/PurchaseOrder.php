<?php

namespace Josephdlmd\FilamentStorybookPh\Stories\Documents;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Josephdlmd\FilamentStorybook\Stories\Documents\PurchaseOrder as CorePurchaseOrder;

/** Stands in for the app's own purchase order in the withholding report: the core one with the ATC withheld on it. */
class PurchaseOrder extends CorePurchaseOrder
{
    protected $table = 'purchase_orders';

    /** @return BelongsTo<WithholdingCode, $this> */
    public function withholdingCode(): BelongsTo
    {
        return $this->belongsTo(WithholdingCode::class, 'withholding_code', 'code');
    }
}
