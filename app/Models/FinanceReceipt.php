<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceReceipt extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'finance_entry_id', 'original_name', 'disk', 'path', 'mime_type', 'size', 'document_date',
        'notes', 'status', 'uploaded_by', 'voided_by', 'voided_at', 'void_reason',
    ];

    protected $casts = [
        'document_date' => 'date',
        'voided_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(FinanceEntry::class, 'finance_entry_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
