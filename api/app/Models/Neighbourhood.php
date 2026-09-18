<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Neighbourhood extends Model
{
    protected $fillable = [
        'code', 'name', 'district_code', 'municipality_code', 'municipality_name',
        'population', 'is_water', 'source_year',
    ];

    protected function casts(): array
    {
        return ['is_water' => 'boolean', 'population' => 'integer', 'source_year' => 'integer'];
    }

    public function crimeCounts(): HasMany
    {
        return $this->hasMany(CrimeCount::class);
    }

    public function crimeScores(): HasMany
    {
        return $this->hasMany(CrimeScore::class);
    }

    /** Filter op een bounding box (minLng,minLat,maxLng,maxLat) in WGS84. */
    public function scopeInBbox(Builder $query, float $minLng, float $minLat, float $maxLng, float $maxLat): Builder
    {
        return $query->whereRaw(
            'geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)',
            [$minLng, $minLat, $maxLng, $maxLat]
        );
    }

    /** Zet de geometrie vanuit een GeoJSON-geometrie (Polygon of MultiPolygon, WGS84). */
    public function setGeometryFromGeoJson(array|string $geometry): void
    {
        $json = is_string($geometry) ? $geometry : json_encode($geometry);
        DB::table('neighbourhoods')->where('id', $this->id)->update([
            'geom' => DB::raw('ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON('.DB::getPdo()->quote($json).'), 4326))'),
        ]);
    }

    /** Komt in aanmerking voor een genormaliseerde score? */
    public function isScoreEligible(): bool
    {
        return ! $this->is_water
            && $this->population !== null
            && $this->population >= (int) config('veiligonderweg.min_population_for_score');
    }
}
