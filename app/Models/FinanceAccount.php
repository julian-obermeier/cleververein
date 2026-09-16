<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceAccount extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'type', 'currency', 'opening_balance', 'is_default', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(FinanceEntry::class);
    }
}
