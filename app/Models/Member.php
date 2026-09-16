<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'public_id', 'person_id', 'member_number', 'status', 'joined_at', 'left_at', 'notes', 'meta',
    ];

    protected $casts = [
        'joined_at' => 'date',
        'left_at' => 'date',
        'meta' => 'array',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function primaryMembership(): HasMany
    {
        return $this->hasMany(Membership::class)->where('is_primary', true);
    }

    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_members')
            ->withPivot(['relationship', 'is_primary_contact'])
            ->withTimestamps();
    }

    public function functionAssignments(): HasMany
    {
        return $this->hasMany(FunctionAssignment::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', 'member');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MemberTag::class, 'member_tag_assignments')
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MemberDocument::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(MemberCommunication::class);
    }
}
