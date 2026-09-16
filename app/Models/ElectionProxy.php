<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ElectionProxy extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'election_id', 'grantor_member_id', 'proxy_member_id', 'voting_weight', 'status', 'issued_at', 'revoked_at', 'notes',
    ];

    protected $casts = [
        'voting_weight' => 'decimal:3',
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $proxy): void {
            if (! $proxy->election_id || ! $proxy->grantor_member_id || ! $proxy->voting_weight) {
                return;
            }

            $grantor = ElectionVoter::query()
                ->where('election_id', $proxy->election_id)
                ->where('member_id', $proxy->grantor_member_id)
                ->first();

            if ($grantor && (float) $proxy->voting_weight > (float) $grantor->voting_weight + 0.0005) {
                throw ValidationException::withMessages([
                    'voting_weight' => 'Das Vollmachtsgewicht darf das eigene Stimmgewicht des Vollmachtgebers nicht überschreiten.',
                ]);
            }
        });
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'grantor_member_id');
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'proxy_member_id');
    }
}
