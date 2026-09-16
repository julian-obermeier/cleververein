<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormAttachment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'form_submission_id', 'form_field_id', 'field_key', 'original_name', 'mime_type',
        'size', 'disk', 'path', 'created_by_user_id',
    ];

    protected $casts = ['size' => 'integer'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
