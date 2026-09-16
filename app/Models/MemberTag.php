<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MemberTag extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'color', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_tag_assignments')
            ->withTimestamps();
    }
}
