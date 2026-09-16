<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = ['public_id', 'name', 'slug', 'status', 'plan', 'trial_ends_at', 'settings'];

    protected $casts = ['settings' => 'array', 'trial_ends_at' => 'datetime', 'suspended_at' => 'datetime'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('status')->withTimestamps();
    }
}
