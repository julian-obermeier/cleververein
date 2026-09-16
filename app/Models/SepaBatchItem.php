<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SepaBatchItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'sepa_batch_id', 'finance_invoice_id', 'sepa_mandate_id', 'amount', 'sequence_type', 'end_to_end_id', 'status',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SepaBatch::class, 'sepa_batch_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id');
    }

    public function mandate(): BelongsTo
    {
        return $this->belongsTo(SepaMandate::class, 'sepa_mandate_id');
    }
}
