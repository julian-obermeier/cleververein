<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
