<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSubmission extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'form_definition_id', 'member_id', 'submitted_by_user_id', 'current_step_id',
        'reference_number', 'submitter_name', 'submitter_email', 'status', 'submitted_at', 'completed_at', 'metadata',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(FormSubmissionStep::class, 'current_step_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(FormAnswer::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FormAttachment::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FormSubmissionStep::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FormSubmissionEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }
}
