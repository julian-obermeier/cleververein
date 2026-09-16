<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionRule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'contribution_rate_id', 'member_type_id', 'organization_unit_id', 'min_age', 'max_age', 'priority', 'is_active',
    ];

    protected $casts = [
        'min_age' => 'integer',
        'max_age' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function rate(): BelongsTo
    {
        return $this->belongsTo(ContributionRate::class, 'contribution_rate_id');
    }

    public function memberType(): BelongsTo
    {
        return $this->belongsTo(MemberType::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }
}
