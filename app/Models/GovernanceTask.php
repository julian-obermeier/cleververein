<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernanceTask extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'meeting_id', 'resolution_id', 'organization_unit_id', 'assigned_member_id', 'title', 'description', 'priority', 'status',
        'due_at', 'completed_at', 'created_by',
    ];

    protected $casts = ['due_at' => 'date', 'completed_at' => 'datetime'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(GovernanceMeeting::class, 'meeting_id');
    }

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(GovernanceResolution::class, 'resolution_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function assignedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'assigned_member_id');
    }
}
