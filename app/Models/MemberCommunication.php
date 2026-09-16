<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCommunication extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'member_id', 'user_id', 'channel', 'direction', 'subject', 'body', 'outcome', 'occurred_at',
    ];

    protected $casts = ['occurred_at' => 'datetime'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
