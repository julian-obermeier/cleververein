<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceEntry extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'entry_number', 'booking_date', 'value_date', 'direction', 'finance_account_id',
        'finance_category_id', 'member_id', 'finance_invoice_id', 'finance_payment_id', 'bank_transaction_id',
        'net_amount', 'tax_amount', 'gross_amount', 'tax_rate', 'description', 'reference', 'source_type',
        'source_id', 'status', 'reversal_of_id', 'created_by', 'posted_at', 'reversed_at', 'notes',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'value_date' => 'date',
        'net_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FinancePayment::class, 'finance_payment_id');
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(FinanceReceipt::class, 'finance_entry_id');
    }
}
