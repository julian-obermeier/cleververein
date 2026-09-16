<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'form_definition_id', 'field_key', 'label', 'field_type', 'position', 'is_required', 'placeholder',
        'help_text', 'options', 'validation', 'condition', 'settings',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_required' => 'boolean',
        'options' => 'array',
        'validation' => 'array',
        'condition' => 'array',
        'settings' => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }
}
