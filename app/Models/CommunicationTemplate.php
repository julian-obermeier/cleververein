<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CommunicationTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['public_id', 'name', 'subject', 'body', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];
}
