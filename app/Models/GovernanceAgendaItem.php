<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovernanceAgendaItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['meeting_id', 'parent_id', 'position', 'item_number', 'title', 'description', 'item_type', 'planned_minutes', 'status'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(GovernanceMeeting::class, 'meeting_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function motions(): HasMany
    {
        return $this->hasMany(GovernanceMotion::class, 'agenda_item_id');
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(GovernanceResolution::class, 'agenda_item_id');
    }
}
