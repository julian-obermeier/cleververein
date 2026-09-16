<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SepaBatch extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'batch_reference', 'collection_date', 'status', 'transaction_count', 'total_amount', 'file_disk', 'file_path', 'file_size', 'created_by', 'generated_at',
    ];

    protected $casts = [
        'collection_date' => 'date',
        'total_amount' => 'decimal:2',
        'generated_at' => 'datetime',
    ];

    public function items(): HasMany { return $this->hasMany(SepaBatchItem::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
