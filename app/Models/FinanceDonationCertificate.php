<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceDonationCertificate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'finance_donation_id', 'certificate_number', 'issue_date', 'status', 'amount',
        'donation_date', 'donation_kind', 'purpose', 'expense_waiver', 'donor_snapshot', 'recipient_snapshot',
        'tax_snapshot', 'issued_by', 'issued_at', 'voided_by', 'voided_at', 'void_reason', 'pdf_disk',
        'pdf_path', 'pdf_size', 'pdf_generated_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'amount' => 'decimal:2',
        'donation_date' => 'date',
        'expense_waiver' => 'boolean',
        'donor_snapshot' => 'array',
        'recipient_snapshot' => 'array',
        'tax_snapshot' => 'array',
        'issued_at' => 'datetime',
        'voided_at' => 'datetime',
        'pdf_generated_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(FinanceDonation::class, 'finance_donation_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
