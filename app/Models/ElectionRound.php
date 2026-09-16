<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectionRound extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'election_office_id', 'round_number', 'status', 'eligible_weight', 'cast_weight', 'invalid_weight',
        'abstain_weight', 'result_status', 'opened_at', 'closed_at', 'finalized_by', 'notes',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'eligible_weight' => 'decimal:3',
        'cast_weight' => 'decimal:3',
        'invalid_weight' => 'decimal:3',
        'abstain_weight' => 'decimal:3',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function office(): BelongsTo
    {
        return $this->belongsTo(ElectionOffice::class, 'election_office_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ElectionCandidateResult::class)->orderBy('rank')->orderByDesc('votes');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
