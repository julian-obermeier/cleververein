<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GovernanceCommittee extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['public_id', 'organization_unit_id', 'name', 'short_name', 'committee_type', 'description', 'status', 'starts_at', 'ends_at', 'settings'];

    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date', 'settings' => 'array'];

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(GovernanceCommitteeMember::class, 'committee_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(GovernanceMeeting::class, 'committee_id');
    }
}
