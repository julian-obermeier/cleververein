<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceDonation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'donation_number', 'member_id', 'donor_name', 'donor_street', 'donor_postal_code',
        'donor_city', 'donor_country', 'donor_email', 'donation_kind', 'amount', 'donation_date', 'purpose',
        'expense_waiver', 'finance_account_id', 'finance_entry_id', 'bank_transaction_id', 'reference', 'notes',
        'status', 'received_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'donation_date' => 'date',
        'expense_waiver' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(FinanceEntry::class, 'finance_entry_id');
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(FinanceDonationCertificate::class, 'finance_donation_id');
    }

    public function collectiveItems(): HasMany
    {
        return $this->hasMany(FinanceDonationCollectiveItem::class, 'finance_donation_id');
    }

    public function getActiveCertificateAttribute(): ?FinanceDonationCertificate
    {
        if ($this->relationLoaded('certificates')) {
            return $this->certificates->firstWhere('status', 'issued');
        }

        return $this->certificates()->where('status', 'issued')->latest('id')->first();
    }
}
