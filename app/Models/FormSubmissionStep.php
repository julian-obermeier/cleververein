<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmissionStep extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'form_submission_id', 'form_workflow_step_id', 'status', 'assigned_user_id', 'due_at',
        'started_at', 'decided_at', 'decision_by_user_id', 'comment',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'started_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(FormWorkflowStep::class, 'form_workflow_step_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function decisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by_user_id');
    }
}
