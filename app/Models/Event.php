<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'event_series_id', 'organization_unit_id', 'title', 'event_type', 'description',
        'starts_at', 'ends_at', 'location', 'online_url', 'status', 'registration_enabled', 'capacity',
        'waitlist_enabled', 'registration_deadline', 'notes', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime', 'ends_at' => 'datetime', 'registration_deadline' => 'datetime',
        'registration_enabled' => 'boolean', 'capacity' => 'integer', 'waitlist_enabled' => 'boolean',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(EventSeries::class, 'event_series_id');
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(CommunicationCampaign::class);
    }
}
