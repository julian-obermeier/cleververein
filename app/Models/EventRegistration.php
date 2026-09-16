<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistration extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'event_id', 'member_id', 'guest_name', 'guest_email', 'status', 'attendance_status',
        'response_token', 'invited_at', 'responded_at', 'checked_in_at', 'notes',
    ];

    protected $casts = [
        'invited_at' => 'datetime', 'responded_at' => 'datetime', 'checked_in_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
