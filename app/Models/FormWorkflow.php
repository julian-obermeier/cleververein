<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormWorkflow extends Model
{
    use BelongsToTenant;

    protected $fillable = ['public_id', 'form_definition_id', 'name', 'is_active', 'settings'];

    protected $casts = ['is_active' => 'boolean', 'settings' => 'array'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FormWorkflowStep::class)->orderBy('position')->orderBy('id');
    }
}
