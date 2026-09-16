<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['public_id', 'name', 'contact_data', 'notes'];

    protected $casts = ['contact_data' => 'array'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'household_members')
            ->withPivot(['relationship', 'is_primary_contact'])
            ->withTimestamps();
    }
}
