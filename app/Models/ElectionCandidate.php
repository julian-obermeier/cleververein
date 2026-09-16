<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectionCandidate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'election_office_id', 'member_id', 'nominated_by_member_id', 'status', 'accepted_at', 'withdrawn_at', 'statement',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function office(): BelongsTo
    {
        return $this->belongsTo(ElectionOffice::class, 'election_office_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function nominator(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'nominated_by_member_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ElectionCandidateResult::class);
    }
}
