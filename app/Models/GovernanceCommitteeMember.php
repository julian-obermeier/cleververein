<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernanceCommitteeMember extends Model
{
    use BelongsToTenant;

    protected $fillable = ['committee_id', 'member_id', 'role_name', 'is_chair', 'has_voting_right', 'status', 'starts_at', 'ends_at'];

    protected $casts = ['is_chair' => 'boolean', 'has_voting_right' => 'boolean', 'starts_at' => 'date', 'ends_at' => 'date'];

    public function committee(): BelongsTo
    {
        return $this->belongsTo(GovernanceCommittee::class, 'committee_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
