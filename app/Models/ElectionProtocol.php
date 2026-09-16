<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectionProtocol extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'public_id', 'election_id', 'version', 'snapshot', 'disk', 'path', 'size', 'generated_by', 'generated_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'snapshot' => 'array',
        'size' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
