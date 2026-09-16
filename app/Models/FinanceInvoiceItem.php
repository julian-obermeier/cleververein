<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceInvoiceItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'finance_invoice_id', 'contribution_rate_id', 'description', 'quantity', 'unit_price', 'tax_rate', 'net_amount', 'tax_amount', 'gross_amount', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id');
    }

    public function contributionRate(): BelongsTo
    {
        return $this->belongsTo(ContributionRate::class);
    }
}
