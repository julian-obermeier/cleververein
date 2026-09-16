<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionOverride extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'member_id', 'contribution_rate_id', 'amount', 'is_exempt', 'valid_from', 'valid_until', 'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_exempt' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(ContributionRate::class, 'contribution_rate_id');
    }
}
