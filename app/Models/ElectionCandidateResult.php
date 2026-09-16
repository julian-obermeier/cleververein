<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectionCandidateResult extends Model
{
    use BelongsToTenant;

    protected $fillable = ['election_round_id', 'election_candidate_id', 'votes', 'rank', 'is_elected'];

    protected $casts = [
        'votes' => 'decimal:3',
        'rank' => 'integer',
        'is_elected' => 'boolean',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(ElectionRound::class, 'election_round_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ElectionCandidate::class, 'election_candidate_id');
    }
}
