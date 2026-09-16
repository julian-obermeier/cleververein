<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GovernanceMeeting extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'public_id', 'committee_id', 'organization_unit_id', 'title', 'meeting_type', 'starts_at', 'ends_at', 'location', 'online_url',
        'status', 'quorum_required', 'quorum_met', 'minutes_status', 'minutes_text', 'minutes_approved_at', 'minutes_approved_by', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime', 'ends_at' => 'datetime', 'quorum_met' => 'boolean', 'minutes_approved_at' => 'datetime',
    ];

    public function committee(): BelongsTo
    {
        return $this->belongsTo(GovernanceCommittee::class, 'committee_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(GovernanceMeetingParticipant::class, 'meeting_id');
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(GovernanceAgendaItem::class, 'meeting_id')->orderBy('position')->orderBy('id');
    }

    public function motions(): HasMany
    {
        return $this->hasMany(GovernanceMotion::class, 'meeting_id');
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(GovernanceResolution::class, 'meeting_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(GovernanceTask::class, 'meeting_id');
    }
}
