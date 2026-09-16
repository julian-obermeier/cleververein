<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MemberSegment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'description', 'criteria', 'is_active'];

    protected $casts = [
        'criteria' => 'array',
        'is_active' => 'boolean',
    ];
}
