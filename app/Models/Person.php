<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['public_id', 'salutation', 'title', 'first_name', 'last_name', 'email', 'birth_date', 'contact_data'];
    protected $casts = ['birth_date' => 'date', 'contact_data' => 'array'];

    public function getDisplayNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
