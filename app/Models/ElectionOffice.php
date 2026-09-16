<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectionOffice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'election_id', 'function_definition_id', 'name', 'seats', 'voting_method', 'majority_type',
        'majority_basis', 'max_rounds', 'allow_abstention', 'sync_function_assignments',
        'term_starts_at', 'term_ends_at', 'position', 'notes',
    ];

    protected $casts = [
        'seats' => 'integer',
        'max_rounds' => 'integer',
        'allow_abstention' => 'boolean',
        'sync_function_assignments' => 'boolean',
        'term_starts_at' => 'date',
        'term_ends_at' => 'date',
        'position' => 'integer',
    ];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function functionDefinition(): BelongsTo
    {
        return $this->belongsTo(FunctionDefinition::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(ElectionCandidate::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(ElectionRound::class)->orderBy('round_number');
    }
}
