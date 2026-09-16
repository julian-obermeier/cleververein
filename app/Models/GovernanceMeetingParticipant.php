<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernanceMeetingParticipant extends Model
{
    use BelongsToTenant;

    protected $fillable = ['meeting_id', 'member_id', 'external_name', 'participant_role', 'attendance_status', 'has_voting_right'];

    protected $casts = ['has_voting_right' => 'boolean'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(GovernanceMeeting::class, 'meeting_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
