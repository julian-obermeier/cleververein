<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmissionEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['form_submission_id', 'event_type', 'user_id', 'data', 'occurred_at'];

    protected $casts = ['data' => 'array', 'occurred_at' => 'datetime'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
