<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FunctionDefinition extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'code', 'category', 'description', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function assignments(): HasMany
    {
        return $this->hasMany(FunctionAssignment::class);
    }
}
