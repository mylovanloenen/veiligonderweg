<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrimeScore extends Model
{
    public $timestamps = false;

    protected $fillable = ['neighbourhood_id', 'year', 'category', 'count', 'rate_per_1000', 'score', 'class', 'computed_at'];

    protected function casts(): array
    {
        return [
            'computed_at' => 'datetime', 'year' => 'integer', 'count' => 'integer',
            'rate_per_1000' => 'float', 'score' => 'integer', 'class' => 'integer',
        ];
    }

    public function neighbourhood(): BelongsTo
    {
        return $this->belongsTo(Neighbourhood::class);
    }
}
