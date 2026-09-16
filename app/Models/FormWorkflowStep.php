<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormWorkflowStep extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'form_workflow_id', 'position', 'name', 'step_type', 'assigned_user_id', 'assigned_role_id',
        'due_days', 'decision_required', 'settings',
    ];

    protected $casts = [
        'position' => 'integer',
        'due_days' => 'integer',
        'decision_required' => 'boolean',
        'settings' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(FormWorkflow::class, 'form_workflow_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'assigned_role_id');
    }

    public function submissionSteps(): HasMany
    {
        return $this->hasMany(FormSubmissionStep::class);
    }
}
