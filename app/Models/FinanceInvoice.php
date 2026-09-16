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
        'public_id', 'member_id', 'household_id', 'invoice_number', 'status', 'invoice_date', 'due_date', 'net_amount', 'tax_amount', 'gross_amount', 'paid_amount', 'currency', 'notes', 'recipient_snapshot', 'pdf_disk', 'pdf_path', 'pdf_size', 'pdf_generated_at', 'created_by', 'issued_by', 'issued_at', 'cancelled_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'net_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'recipient_snapshot' => 'array',
        'pdf_generated_at' => 'datetime',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
    public function items(): HasMany { return $this->hasMany(FinanceInvoiceItem::class); }
    public function payments(): HasMany { return $this->hasMany(FinancePayment::class); }
    public function dunnings(): HasMany { return $this->hasMany(FinanceDunning::class); }
    public function creditNotes(): HasMany { return $this->hasMany(FinanceCreditNote::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }

    public function getCreditedAmountAttribute(): float
    {
        if ($this->relationLoaded('creditNotes')) {
            return (float) $this->creditNotes->where('status', 'issued')->sum('amount');
        }

        return (float) $this->creditNotes()->where('status', 'issued')->sum('amount');
    }

    public function getOpenAmountAttribute(): float
    {
        return max(0, (float) $this->gross_amount - (float) $this->paid_amount - $this->credited_amount);
    }
}
