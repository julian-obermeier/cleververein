<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunctionAssignment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['member_id', 'function_definition_id', 'organization_unit_id', 'starts_at', 'ends_at', 'notes'];

    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(FunctionDefinition::class, 'function_definition_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }
}
