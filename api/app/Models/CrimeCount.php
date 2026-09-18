<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrimeCount extends Model
{
    public $timestamps = false;

    protected $fillable = ['neighbourhood_id', 'year', 'crime_type_code', 'count', 'imported_at'];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime', 'year' => 'integer', 'count' => 'integer'];
    }

    public function neighbourhood(): BelongsTo
    {
        return $this->belongsTo(Neighbourhood::class);
    }
}
