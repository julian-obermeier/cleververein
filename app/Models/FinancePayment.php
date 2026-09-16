<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancePayment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'finance_invoice_id', 'member_id', 'amount', 'paid_at', 'method', 'reference', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
