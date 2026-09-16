<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FinanceSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'creditor_name', 'street', 'postal_code', 'city', 'country', 'tax_number', 'vat_id', 'creditor_id',
        'iban', 'bic', 'payment_terms_days', 'invoice_footer', 'donation_receipts_enabled', 'tax_notice_type',
        'tax_office', 'tax_notice_date', 'tax_notice_reference', 'tax_notice_years', 'tax_exempt_purposes',
        'membership_contributions_deductible',
    ];

    protected $casts = [
        'iban' => 'encrypted',
        'bic' => 'encrypted',
        'payment_terms_days' => 'integer',
        'donation_receipts_enabled' => 'boolean',
        'tax_notice_date' => 'date',
        'membership_contributions_deductible' => 'boolean',
    ];

    public function getMaskedIbanAttribute(): ?string
    {
        if (! $this->iban) {
            return null;
        }
        $iban = preg_replace('/\s+/', '', (string) $this->iban);

        return strlen($iban) > 8 ? substr($iban, 0, 4).' •••• •••• '.substr($iban, -4) : $iban;
    }

    public function getDonationReceiptReadyAttribute(): bool
    {
        return (bool) $this->donation_receipts_enabled
            && filled($this->creditor_name)
            && filled($this->street)
            && filled($this->postal_code)
            && filled($this->city)
            && filled($this->tax_number)
            && filled($this->tax_notice_type)
            && filled($this->tax_office)
            && filled($this->tax_notice_date)
            && filled($this->tax_exempt_purposes);
    }
}
