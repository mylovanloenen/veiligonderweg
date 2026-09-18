<?php

namespace Database\Seeders;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Testmeldingen rond Amsterdam zodat de kaart direct gevuld is. */
class DemoIncidentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@veiligonderweg.local'],
            ['name' => 'Demo', 'password' => 'demo1234']
        );
        $categories = IncidentCategory::all()->keyBy('slug');

        $demo = [
            ['overig', 52.3791, 4.9003, 'Groep hangt rond bij de ingang van het station, voelt onprettig.', 10],
            ['slechte_verlichting', 52.3660, 4.8760, 'Lantaarns langs het pad in het park zijn uit.', 45],
            ['intimidatie', 52.3702, 4.8952, 'Werd nageroepen en gevolgd over de brug.', 20],
            ['beroving', 52.3585, 4.8590, 'Telefoon uit handen getrokken vanaf een scooter.', 35],
            ['geweld', 52.3730, 4.8930, 'Vechtpartij voor een cafe, politie is gebeld.', 5],
            ['slechte_verlichting', 52.3390, 4.9160, 'Fietstunnel is volledig donker.', 90],
            ['overig', 52.3545, 4.9420, 'Auto rijdt herhaaldelijk heel hard door de woonstraat.', 60],
            ['intimidatie', 52.3865, 4.8720, 'Iemand blokkeert het fietspad en schreeuwt naar passanten.', 15],
            ['beroving', 52.3125, 4.9470, 'Fiets gestolen terwijl ik boodschappen deed.', 70],
            ['overig', 52.3620, 4.9060, 'Glas over de hele stoep, uitkijken met fietsen.', 110],
        ];

        foreach ($demo as [$slug, $lat, $lng, $text, $minutesAgo]) {
            $category = $categories[$slug];
            $createdAt = now()->subMinutes($minutesAgo);
            $incident = Incident::createAt([
                'incident_category_id' => $category->id,
                'user_id' => $user->id,
                'description' => $text,
                'expires_at' => $createdAt->copy()->addMinutes($category->ttl_minutes),
            ], $lat, $lng);
            $incident->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt, 'confirmations' => random_int(0, 4)])->save();
        }
    }
}
