<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceInvoice extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'public_id', 'member_id', 'invoice_number', 'status', 'invoice_date', 'due_date', 'net_amount', 'tax_amount', 'gross_amount', 'paid_amount', 'currency', 'notes', 'created_by', 'issued_by', 'issued_at', 'cancelled_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'net_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FinanceInvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinancePayment::class);
    }

    public function dunnings(): HasMany
    {
        return $this->hasMany(FinanceDunning::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function getOpenAmountAttribute(): float
    {
        return max(0, (float) $this->gross_amount - (float) $this->paid_amount);
    }
}
