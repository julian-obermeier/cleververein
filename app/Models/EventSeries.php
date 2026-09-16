<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSeries extends Model
{
    use BelongsToTenant;

    protected $table = 'event_series';

    protected $fillable = [
        'public_id', 'organization_unit_id', 'title', 'event_type', 'description', 'location', 'online_url',
        'recurrence_type', 'recurrence_interval', 'recurrence_count', 'recurrence_until',
        'registration_enabled', 'capacity', 'waitlist_enabled', 'status', 'created_by',
    ];

    protected $casts = [
        'recurrence_interval' => 'integer', 'recurrence_count' => 'integer', 'recurrence_until' => 'date',
        'registration_enabled' => 'boolean', 'capacity' => 'integer', 'waitlist_enabled' => 'boolean',
    ];

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class)->orderBy('starts_at');
    }
}
