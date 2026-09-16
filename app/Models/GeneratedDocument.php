<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'document_template_id', 'member_id', 'generated_by', 'title', 'file_name', 'disk', 'path', 'mime_type', 'size', 'context', 'generated_at',
    ];

    protected $casts = [
        'context' => 'array',
        'generated_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
