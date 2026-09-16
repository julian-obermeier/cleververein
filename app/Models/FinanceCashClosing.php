<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceCashClosing extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'finance_account_id', 'closing_date', 'system_balance', 'counted_balance', 'difference',
        'denomination_counts', 'notes', 'status', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'system_balance' => 'decimal:2',
        'counted_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'denomination_counts' => 'array',
        'closed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
