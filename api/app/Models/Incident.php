<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_category_id', 'user_id', 'description', 'expires_at', 'hidden_at', 'anonymised_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime', 'hidden_at' => 'datetime', 'anonymised_at' => 'datetime',
            'confirmations' => 'integer', 'disputes' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class, 'incident_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(IncidentVote::class);
    }

    /** Actief (niet verlopen) en niet verborgen. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at')->where('expires_at', '>', now());
    }

    /** Alleen de geo-kolommen als lat/lng selecteren (plus alles). */
    public function scopeWithCoordinates(Builder $query): Builder
    {
        return $query->addSelect('incidents.*')
            ->selectRaw('ST_Y(location::geometry) AS lat, ST_X(location::geometry) AS lng');
    }

    /** Binnen X meter van een coordinaat (PostGIS ST_DWithin op geography). */
    public function scopeWithinMeters(Builder $query, float $lat, float $lng, int $radiusM): Builder
    {
        $point = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';

        return $query
            ->whereRaw("ST_DWithin(location, {$point}, ?)", [$lng, $lat, $radiusM])
            ->selectRaw("ST_Distance(location, {$point}) AS distance_m", [$lng, $lat])
            ->orderBy('distance_m');
    }

    public function scopeInBbox(Builder $query, float $minLng, float $minLat, float $maxLng, float $maxLat): Builder
    {
        return $query->whereRaw(
            'location::geometry && ST_MakeEnvelope(?, ?, ?, ?, 4326)',
            [$minLng, $minLat, $maxLng, $maxLat]
        );
    }

    /** Maak een melding aan met een geography-punt. */
    public static function createAt(array $attributes, float $lat, float $lng): self
    {
        $incident = new self($attributes);
        $incident->setRawAttributes(array_merge($incident->getAttributes(), [
            'location' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)::geography', $lng, $lat)),
        ]));
        $incident->save();

        return $incident->fresh();
    }

    public function maxExpiresAt(): CarbonInterface
    {
        $multiplier = (int) config('veiligonderweg.incidents.max_ttl_multiplier');

        return $this->created_at->copy()->addMinutes($this->category->ttl_minutes * $multiplier);
    }

    /** Bevestiging: teller omhoog en vervaltijd verlengen tot het maximum. */
    public function applyConfirmation(): void
    {
        $this->confirmations++;
        $extended = $this->expires_at->copy()->addMinutes((int) config('veiligonderweg.incidents.confirm_extends_minutes'));
        $this->expires_at = $extended->min($this->maxExpiresAt());
        $this->save();
    }

    /** Weerlegging: teller omhoog en eventueel verbergen. */
    public function applyDispute(): void
    {
        $this->disputes++;
        $threshold = (int) config('veiligonderweg.incidents.hide_after_disputes');
        if ($this->disputes >= $threshold && $this->disputes > $this->confirmations) {
            $this->hidden_at = now();
        }
        $this->save();
    }
}
