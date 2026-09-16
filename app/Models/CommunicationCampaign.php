<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationCampaign extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'event_id', 'template_id', 'member_segment_id', 'organization_unit_id', 'name', 'channel',
        'target_type', 'subject', 'body', 'status', 'recipient_count', 'sent_count', 'failed_count',
        'prepared_at', 'started_at', 'completed_at', 'created_by',
    ];

    protected $casts = [
        'recipient_count' => 'integer', 'sent_count' => 'integer', 'failed_count' => 'integer',
        'prepared_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'template_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(MemberSegment::class, 'member_segment_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CommunicationRecipient::class, 'campaign_id');
    }
}
