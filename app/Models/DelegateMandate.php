<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelegateMandate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'member_id', 'represented_organization_unit_id', 'receiving_organization_unit_id',
        'mandate_number', 'voting_weight', 'status', 'starts_at', 'ends_at', 'notes',
    ];

    protected $casts = [
        'voting_weight' => 'decimal:3',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function representedOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'represented_organization_unit_id');
    }

    public function receivingOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'receiving_organization_unit_id');
    }
}
