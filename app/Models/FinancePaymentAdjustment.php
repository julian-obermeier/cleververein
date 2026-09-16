<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancePaymentAdjustment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'finance_payment_id', 'finance_invoice_id', 'member_id', 'type', 'amount', 'fee_amount',
        'adjustment_date', 'reason', 'reference', 'bank_transaction_id', 'reversal_entry_id', 'fee_entry_id',
        'status', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'adjustment_date' => 'date',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FinancePayment::class, 'finance_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function reversalEntry(): BelongsTo
    {
        return $this->belongsTo(FinanceEntry::class, 'reversal_entry_id');
    }

    public function feeEntry(): BelongsTo
    {
        return $this->belongsTo(FinanceEntry::class, 'fee_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
