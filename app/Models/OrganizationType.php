<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'slug', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function units(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class);
    }
}
