<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceDonationCollectiveCertificate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'certificate_number', 'issue_date', 'period_from', 'period_to', 'status', 'total_amount',
        'donor_snapshot', 'recipient_snapshot', 'tax_snapshot', 'issued_by', 'issued_at', 'voided_by',
        'voided_at', 'void_reason', 'pdf_disk', 'pdf_path', 'pdf_size', 'pdf_generated_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'total_amount' => 'decimal:2',
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

    public function items(): HasMany
    {
        return $this->hasMany(FinanceDonationCollectiveItem::class, 'collective_certificate_id');
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
