<?php

namespace Tests\Support;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\Neighbourhood;
use App\Models\User;

trait CreatesGeoData
{
    protected function seedCategories(): void
    {
        $this->seed(\Database\Seeders\IncidentCategorySeeder::class);
    }

    /** Vierkante buurt van ongeveer `sizeDeg` graden rond een middelpunt. */
    protected function createSquareNeighbourhood(string $code, float $lng, float $lat, float $sizeDeg = 0.01, array $attrs = []): Neighbourhood
    {
        $h = $sizeDeg / 2;
        $n = Neighbourhood::create(array_merge([
            'code' => $code,
            'name' => 'Buurt '.$code,
            'municipality_code' => 'GM0363',
            'municipality_name' => 'Amsterdam',
            'population' => 1000,
            'is_water' => false,
            'source_year' => 2025,
        ], $attrs));

        $n->setGeometryFromGeoJson([
            'type' => 'Polygon',
            'coordinates' => [[
                [$lng - $h, $lat - $h], [$lng + $h, $lat - $h], [$lng + $h, $lat + $h], [$lng - $h, $lat + $h], [$lng - $h, $lat - $h],
            ]],
        ]);

        return $n->fresh();
    }

    protected function createIncident(User $user, float $lat, float $lng, array $attrs = []): Incident
    {
        $category = IncidentCategory::where('slug', $attrs['category'] ?? 'overig')->firstOrFail();

        return Incident::createAt(array_merge([
            'incident_category_id' => $category->id,
            'user_id' => $user->id,
            'description' => 'Testmelding',
            'expires_at' => now()->addMinutes($category->ttl_minutes),
        ], array_diff_key($attrs, ['category' => 1])), $lat, $lng);
    }
}
