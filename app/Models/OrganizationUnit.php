<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationUnit extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['public_id', 'organization_type_id', 'parent_id', 'name', 'short_name', 'slug', 'status', 'founded_at', 'dissolved_at', 'contact_data', 'registry_data', 'settings'];

    protected $casts = ['founded_at' => 'date', 'dissolved_at' => 'date', 'contact_data' => 'array', 'registry_data' => 'array', 'settings' => 'array'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(OrganizationType::class, 'organization_type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function descendants(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'organization_closure', 'ancestor_id', 'descendant_id')->withPivot('depth');
    }
}
