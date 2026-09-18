<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentVote extends Model
{
    public const CONFIRM = 'confirm';

    public const DISPUTE = 'dispute';

    protected $fillable = ['incident_id', 'user_id', 'type'];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
