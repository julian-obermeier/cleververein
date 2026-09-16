<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovernanceResolution extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'meeting_id', 'agenda_item_id', 'motion_id', 'organization_unit_id', 'resolution_number', 'title', 'resolution_text',
        'decision_status', 'voting_method', 'votes_yes', 'votes_no', 'votes_abstain', 'votes_invalid', 'effective_date', 'created_by',
    ];

    protected $casts = ['effective_date' => 'date'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(GovernanceMeeting::class, 'meeting_id');
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(GovernanceAgendaItem::class, 'agenda_item_id');
    }

    public function motion(): BelongsTo
    {
        return $this->belongsTo(GovernanceMotion::class, 'motion_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(GovernanceTask::class, 'resolution_id');
    }
}
