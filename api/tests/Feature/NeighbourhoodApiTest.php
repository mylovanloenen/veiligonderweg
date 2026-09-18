<?php

namespace Tests\Feature;

use App\Models\CrimeScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGeoData;
use Tests\TestCase;

class NeighbourhoodApiTest extends TestCase
{
    use CreatesGeoData, RefreshDatabase;

    public function test_returns_geojson_with_scores_filtered_by_bbox(): void
    {
        $inside = $this->createSquareNeighbourhood('BU0363AA01', 4.90, 52.37);
        $outside = $this->createSquareNeighbourhood('BU0363ZZ99', 5.50, 52.90);

        CrimeScore::create(['neighbourhood_id' => $inside->id, 'year' => 2025, 'category' => 'violence', 'count' => 12, 'rate_per_1000' => 12.0, 'score' => 80, 'class' => 5, 'computed_at' => now()]);
        CrimeScore::create(['neighbourhood_id' => $inside->id, 'year' => 2025, 'category' => 'total', 'count' => 40, 'rate_per_1000' => 40.0, 'score' => 20, 'class' => 2, 'computed_at' => now()]);

        $response = $this->getJson('/api/v1/neighbourhoods?bbox=4.85,52.33,4.95,52.41&category=violence');

        $response->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('features.0.properties.code', 'BU0363AA01')
            ->assertJsonPath('features.0.properties.score', 80)
            ->assertJsonPath('features.0.properties.class', 5)
            ->assertJsonPath('features.0.properties.count', 12);

        $this->assertContains($response->json('features.0.geometry.type'), ['Polygon', 'MultiPolygon']);
        $this->assertStringNotContainsString($outside->code, $response->getContent());
    }

    public function test_neighbourhood_without_score_is_returned_with_null_class(): void
    {
        $this->createSquareNeighbourhood('BU0363AA02', 4.90, 52.37, 0.01, ['population' => null]);

        $this->getJson('/api/v1/neighbourhoods?category=total')
            ->assertOk()
            ->assertJsonPath('features.0.properties.class', null)
            ->assertJsonPath('features.0.properties.score', null);
    }

    public function test_rejects_unknown_category(): void
    {
        $this->getJson('/api/v1/neighbourhoods?category=nope')->assertStatus(422);
    }

    public function test_show_returns_all_categories(): void
    {
        $n = $this->createSquareNeighbourhood('BU0363AA03', 4.90, 52.37);
        CrimeScore::create(['neighbourhood_id' => $n->id, 'year' => 2025, 'category' => 'burglary', 'count' => 3, 'rate_per_1000' => 3.0, 'score' => 40, 'class' => 3, 'computed_at' => now()]);

        $this->getJson('/api/v1/neighbourhoods/BU0363AA03')
            ->assertOk()
            ->assertJsonPath('name', 'Buurt BU0363AA03')
            ->assertJsonPath('scores.burglary.score', 40);
    }
}
