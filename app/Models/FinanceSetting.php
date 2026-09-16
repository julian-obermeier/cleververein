<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FinanceSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'creditor_name', 'street', 'postal_code', 'city', 'country', 'tax_number', 'vat_id', 'creditor_id', 'iban', 'bic', 'payment_terms_days', 'invoice_footer',
    ];

    protected $casts = [
        'iban' => 'encrypted',
        'bic' => 'encrypted',
        'payment_terms_days' => 'integer',
    ];

    public function getMaskedIbanAttribute(): ?string
    {
        if (! $this->iban) {
            return null;
        }
        $iban = preg_replace('/\s+/', '', (string) $this->iban);

        return strlen($iban) > 8 ? substr($iban, 0, 4).' •••• •••• '.substr($iban, -4) : $iban;
    }
}
