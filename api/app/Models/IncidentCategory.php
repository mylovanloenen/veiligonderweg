<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncidentCategory extends Model
{
    protected $fillable = ['slug', 'name', 'ttl_minutes'];

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }
}
