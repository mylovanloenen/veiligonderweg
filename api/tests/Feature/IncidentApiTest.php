<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\CreatesGeoData;
use Tests\TestCase;

class IncidentApiTest extends TestCase
{
    use CreatesGeoData, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCategories();
        $this->user = User::factory()->create();
        RateLimiter::clear('incidents:'.$this->user->id);
    }

    public function test_radius_query_uses_distance_and_excludes_far_incidents(): void
    {
        // Dam, Amsterdam
        $near = $this->createIncident($this->user, 52.3731, 4.8932);          // ~0 m
        $mid = $this->createIncident($this->user, 52.3780, 4.8932);           // ~545 m noordelijk
        $far = $this->createIncident($this->user, 52.3900, 4.8932);           // ~1880 m noordelijk

        $response = $this->getJson('/api/v1/incidents?lat=52.3731&lng=4.8932&radius=1000')->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$near->id, $mid->id], $ids);
        $this->assertNotContains($far->id, $ids);

        $this->assertLessThan(5, $response->json('data.0.distance_m'));
        $this->assertEqualsWithDelta(545, $response->json('data.1.distance_m'), 30);
    }

    public function test_expired_and_hidden_incidents_are_not_listed(): void
    {
        $active = $this->createIncident($this->user, 52.3731, 4.8932);
        $expired = $this->createIncident($this->user, 52.3731, 4.8932, ['expires_at' => now()->subMinute()]);
        $hidden = $this->createIncident($this->user, 52.3731, 4.8932, ['hidden_at' => now()]);

        $ids = collect($this->getJson('/api/v1/incidents?lat=52.3731&lng=4.8932')->json('data'))->pluck('id')->all();

        $this->assertSame([$active->id], $ids);
    }

    public function test_incident_expires_after_category_ttl(): void
    {
        $this->travelTo(now());
        $incident = $this->createIncident($this->user, 52.3731, 4.8932, ['category' => 'beroving']); // TTL 120 min

        $this->travel(119)->minutes();
        $this->assertCount(1, Incident::visible()->get());

        $this->travel(2)->minutes();
        $this->assertCount(0, Incident::visible()->get());
        $this->assertSame($incident->id, Incident::first()->id, 'record blijft bestaan tot anonimisering/opschoning');
    }

    public function test_bbox_query(): void
    {
        $in = $this->createIncident($this->user, 52.3731, 4.8932);
        $this->createIncident($this->user, 52.50, 4.60);

        $ids = collect($this->getJson('/api/v1/incidents?bbox=4.85,52.35,4.95,52.40')->json('data'))->pluck('id')->all();
        $this->assertSame([$in->id], $ids);
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/incidents', ['category' => 'overig', 'lat' => 52.37, 'lng' => 4.89])->assertStatus(401);
    }

    public function test_store_creates_incident_with_ttl_from_category(): void
    {
        $this->travelTo(now());

        $this->actingAs($this->user)
            ->postJson('/api/v1/incidents', [
                'category' => 'slechte_verlichting', 'lat' => 52.37, 'lng' => 4.89,
                'description' => 'Lantaarns zijn uit in de tunnel.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.category.slug', 'slechte_verlichting')
            ->assertJsonPath('data.lat', 52.37)
            ->assertJsonPath('data.lng', 4.89)
            ->assertJsonPath('data.expires_at', now()->addMinutes(720)->toIso8601String());
    }

    public function test_store_rejects_personal_data_and_long_text(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/incidents', ['category' => 'overig', 'lat' => 52.37, 'lng' => 4.89, 'description' => 'bel 0612345678'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);

        $this->actingAs($this->user)
            ->postJson('/api/v1/incidents', ['category' => 'overig', 'lat' => 52.37, 'lng' => 4.89, 'description' => str_repeat('a', 201)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_store_is_rate_limited(): void
    {
        $limit = (int) config('veiligonderweg.incidents.rate_limit_per_hour');
        for ($i = 0; $i < $limit; $i++) {
            $this->actingAs($this->user)
                ->postJson('/api/v1/incidents', ['category' => 'overig', 'lat' => 52.37, 'lng' => 4.89])
                ->assertCreated();
        }

        $this->actingAs($this->user)
            ->postJson('/api/v1/incidents', ['category' => 'overig', 'lat' => 52.37, 'lng' => 4.89])
            ->assertStatus(429);
    }

    public function test_confirm_extends_expiry_and_dispute_hides(): void
    {
        $this->travelTo(now());
        $incident = $this->createIncident($this->user, 52.37, 4.89, ['category' => 'overig']); // TTL 120
        $originalExpiry = $incident->expires_at;

        $voter = User::factory()->create();
        $this->actingAs($voter)->postJson("/api/v1/incidents/{$incident->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.confirmations', 1)
            ->assertJsonPath('data.expires_at', $originalExpiry->copy()->addMinutes(30)->toIso8601String());

        // Zelfde gebruiker mag niet nog eens stemmen.
        $this->actingAs($voter)->postJson("/api/v1/incidents/{$incident->id}/dispute")->assertStatus(409);
        // Eigen melding beoordelen mag niet.
        $this->actingAs($this->user)->postJson("/api/v1/incidents/{$incident->id}/confirm")->assertStatus(422);

        foreach (range(1, 3) as $i) {
            $this->actingAs(User::factory()->create())->postJson("/api/v1/incidents/{$incident->id}/dispute")->assertOk();
        }
        // 3 weerleggingen > 1 bevestiging -> verborgen.
        $this->assertNotNull($incident->fresh()->hidden_at);
        $this->assertCount(0, Incident::visible()->get());
    }

    public function test_confirmations_cannot_extend_beyond_twice_the_ttl(): void
    {
        $this->travelTo(now());
        $incident = $this->createIncident($this->user, 52.37, 4.89, ['category' => 'overig']);
        $max = $incident->created_at->copy()->addMinutes(240);

        foreach (range(1, 6) as $i) {
            $this->actingAs(User::factory()->create())->postJson("/api/v1/incidents/{$incident->id}/confirm")->assertOk();
        }

        $this->assertTrue($incident->fresh()->expires_at->equalTo($max));
    }
}
