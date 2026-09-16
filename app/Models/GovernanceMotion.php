<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernanceMotion extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'meeting_id', 'agenda_item_id', 'organization_unit_id', 'proposer_member_id', 'motion_number', 'title', 'motion_text',
        'rationale', 'proposer_name', 'status', 'submitted_at',
    ];

    protected $casts = ['submitted_at' => 'datetime'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(GovernanceMeeting::class, 'meeting_id');
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(GovernanceAgendaItem::class, 'agenda_item_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'proposer_member_id');
    }
}
