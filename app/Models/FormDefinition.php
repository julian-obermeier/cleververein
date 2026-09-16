<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormDefinition extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'public_id', 'organization_unit_id', 'name', 'slug', 'description', 'form_type', 'status', 'public_token',
        'allow_anonymous', 'require_member', 'submission_prefix', 'success_message', 'settings', 'version',
        'published_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'allow_anonymous' => 'boolean',
        'require_member' => 'boolean',
        'settings' => 'array',
        'version' => 'integer',
        'published_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('position')->orderBy('id');
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(FormWorkflow::class)->orderByDesc('is_active')->orderBy('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
