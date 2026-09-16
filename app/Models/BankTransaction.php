<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankTransaction extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'bank_import_batch_id', 'booking_date', 'value_date', 'amount', 'currency', 'payer_name', 'payer_iban',
        'reference', 'external_id', 'status', 'finance_invoice_id', 'member_id', 'finance_payment_id',
        'match_confidence', 'match_reason', 'end_to_end_id', 'mandate_reference', 'bank_transaction_code',
        'return_reason_code', 'return_reason_text', 'raw_details',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'value_date' => 'date',
        'amount' => 'decimal:2',
        'payer_iban' => 'encrypted',
        'match_confidence' => 'integer',
        'raw_details' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BankImportBatch::class, 'bank_import_batch_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FinancePayment::class, 'finance_payment_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(FinancePaymentAdjustment::class, 'bank_transaction_id');
    }
}
