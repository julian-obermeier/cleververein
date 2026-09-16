<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectionVoter extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'election_id', 'member_id', 'delegate_mandate_id', 'source', 'voting_weight', 'status', 'checked_in_at', 'notes',
    ];

    protected $casts = [
        'voting_weight' => 'decimal:3',
        'checked_in_at' => 'datetime',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function delegateMandate(): BelongsTo
    {
        return $this->belongsTo(DelegateMandate::class);
    }
}
