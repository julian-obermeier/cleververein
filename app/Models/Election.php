<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Election extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'public_id', 'organization_unit_id', 'governance_meeting_id', 'title', 'election_date', 'status',
        'voter_basis', 'allow_proxies', 'notes', 'created_by', 'finalized_at', 'finalized_by',
    ];

    protected $casts = [
        'election_date' => 'date',
        'allow_proxies' => 'boolean',
        'finalized_at' => 'datetime',
    ];

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function governanceMeeting(): BelongsTo
    {
        return $this->belongsTo(GovernanceMeeting::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function offices(): HasMany
    {
        return $this->hasMany(ElectionOffice::class)->orderBy('position')->orderBy('id');
    }

    public function voters(): HasMany
    {
        return $this->hasMany(ElectionVoter::class);
    }

    public function proxies(): HasMany
    {
        return $this->hasMany(ElectionProxy::class);
    }

    public function protocols(): HasMany
    {
        return $this->hasMany(ElectionProtocol::class)->orderByDesc('version');
    }
}
