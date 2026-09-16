<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SepaMandate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'member_id', 'mandate_reference', 'account_holder', 'iban', 'bic', 'signed_at', 'revoked_at', 'status', 'collection_count', 'last_collected_at',
    ];

    protected $casts = [
        'iban' => 'encrypted',
        'bic' => 'encrypted',
        'signed_at' => 'date',
        'revoked_at' => 'date',
        'collection_count' => 'integer',
        'last_collected_at' => 'date',
    ];

    public function member(): BelongsTo { return $this->belongsTo(Member::class); }

    public function getMaskedIbanAttribute(): string
    {
        $iban = preg_replace('/\s+/', '', (string) $this->iban);
        if (strlen($iban) <= 8) {
            return $iban;
        }

        return substr($iban, 0, 4).' •••• •••• '.substr($iban, -4);
    }
}
