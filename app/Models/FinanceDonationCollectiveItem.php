<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceDonationCollectiveItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'collective_certificate_id', 'finance_donation_id', 'donation_date', 'amount', 'donation_kind',
        'purpose', 'expense_waiver',
    ];

    protected $casts = [
        'donation_date' => 'date',
        'amount' => 'decimal:2',
        'expense_waiver' => 'boolean',
    ];

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(FinanceDonationCollectiveCertificate::class, 'collective_certificate_id');
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(FinanceDonation::class, 'finance_donation_id');
    }
}
