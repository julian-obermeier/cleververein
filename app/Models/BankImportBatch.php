<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankImportBatch extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'original_name', 'file_hash', 'row_count', 'matched_count', 'imported_by',
    ];

    public function transactions(): HasMany { return $this->hasMany(BankTransaction::class); }
    public function importer(): BelongsTo { return $this->belongsTo(User::class, 'imported_by'); }
}
