<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesGeoData;
use Tests\TestCase;

class AnonymiseIncidentsTest extends TestCase
{
    use CreatesGeoData, RefreshDatabase;

    public function test_expired_incidents_are_anonymised_and_later_deleted(): void
    {
        $this->seedCategories();
        $user = User::factory()->create();

        $fresh = $this->createIncident($user, 52.37, 4.89);
        $old = $this->createIncident($user, 52.37, 4.89, ['expires_at' => now()->subDays(8)]);
        $ancient = $this->createIncident($user, 52.37, 4.89, ['expires_at' => now()->subDays(31)]);

        $this->artisan('incidents:anonymise')->assertSuccessful();

        $this->assertSame($user->id, $fresh->fresh()->user_id);
        $this->assertSame('Testmelding', $fresh->fresh()->description);

        $this->assertNull($old->fresh()->user_id);
        $this->assertNull($old->fresh()->description);
        $this->assertNotNull($old->fresh()->anonymised_at);

        $this->assertNull(Incident::find($ancient->id));
    }
}
