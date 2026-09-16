<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContributionRate extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['name', 'code', 'amount', 'interval', 'scope', 'billing_month', 'description', 'is_active'];

    protected $casts = [
        'amount' => 'decimal:2',
        'billing_month' => 'integer',
        'is_active' => 'boolean',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(ContributionRule::class);
    }
}
