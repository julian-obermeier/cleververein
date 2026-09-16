<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'slug', 'is_system'];
    protected $casts = ['is_system' => 'boolean'];
    public function permissions(): BelongsToMany { return $this->belongsToMany(Permission::class); }
}
