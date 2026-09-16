<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceDunning extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'finance_invoice_id', 'level', 'dunned_at', 'fee', 'status', 'notes', 'created_by', 'pdf_disk', 'pdf_path', 'pdf_size', 'pdf_generated_at',
    ];

    protected $casts = [
        'level' => 'integer',
        'dunned_at' => 'date',
        'fee' => 'decimal:2',
        'pdf_generated_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
