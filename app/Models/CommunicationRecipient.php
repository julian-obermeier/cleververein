<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationRecipient extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'campaign_id', 'member_id', 'event_registration_id', 'recipient_name', 'recipient_email',
        'status', 'attempts', 'sent_at', 'error_message',
    ];

    protected $casts = ['attempts' => 'integer', 'sent_at' => 'datetime'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommunicationCampaign::class, 'campaign_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }
}
