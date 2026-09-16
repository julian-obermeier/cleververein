<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

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

    public function adjustments(): HasMany
    {
        return $this->hasMany(FinancePaymentAdjustment::class, 'finance_payment_id');
    }

    public function getAdjustedAmountAttribute(): float
    {
        if ($this->relationLoaded('adjustments')) {
            return (float) $this->adjustments->where('status', 'posted')->sum('amount');
        }

        return (float) $this->adjustments()->where('status', 'posted')->sum('amount');
    }

    public function getEffectiveAmountAttribute(): float
    {
        return max(0, (float) $this->amount - $this->adjusted_amount);
    }
}
