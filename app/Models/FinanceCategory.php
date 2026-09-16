<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceCategory extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'direction', 'default_tax_rate', 'is_active', 'sort_order', 'datev_account',
    ];

    protected $casts = [
        'default_tax_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(FinanceEntry::class);
    }
}
