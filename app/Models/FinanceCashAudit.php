<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceCashAudit extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'finance_account_id', 'period_start', 'period_end', 'result', 'entry_count',
        'expected_balance', 'counted_balance', 'difference', 'findings', 'audited_by', 'audited_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'expected_balance' => 'decimal:2',
        'counted_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'audited_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audited_by');
    }
}
