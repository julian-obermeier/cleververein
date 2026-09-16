<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceCreditNote extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'finance_invoice_id', 'member_id', 'household_id', 'credit_number', 'status', 'credit_date', 'amount', 'reason', 'recipient_snapshot', 'created_by', 'issued_by', 'issued_at', 'cancelled_at', 'pdf_disk', 'pdf_path', 'pdf_size', 'pdf_generated_at',
    ];

    protected $casts = [
        'credit_date' => 'date',
        'amount' => 'decimal:2',
        'recipient_snapshot' => 'array',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'pdf_generated_at' => 'datetime',
    ];

    public function invoice(): BelongsTo { return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id'); }
    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function household(): BelongsTo { return $this->belongsTo(Household::class); }
}
