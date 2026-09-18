<?php

namespace Tests\Feature;

use App\Models\CrimeScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesGeoData;
use Tests\TestCase;

class CrimeImportTest extends TestCase
{
    use CreatesGeoData, RefreshDatabase;

    public function test_import_and_compute_scores_from_faked_odata(): void
    {
        $a = $this->createSquareNeighbourhood('BU0363AA01', 4.90, 52.37, 0.01, ['population' => 1000]);
        $b = $this->createSquareNeighbourhood('BU0363AA02', 4.92, 52.37, 0.01, ['population' => 2000]);
        $water = $this->createSquareNeighbourhood('BU03639997', 4.94, 52.37, 0.01, ['population' => null, 'is_water' => true]);

        Http::fake(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $q);
            preg_match("/SoortMisdrijf eq '([^']+)'/", $q['$filter'], $m);
            $code = trim($m[1]);
            $count = $code === '1.4.6' ? 5 : 1;

            return Http::response(['value' => [
                ['WijkenEnBuurten' => 'BU0363AA01', 'GeregistreerdeMisdrijven_1' => $count],
                ['WijkenEnBuurten' => 'BU0363AA02', 'GeregistreerdeMisdrijven_1' => $count],
                ['WijkenEnBuurten' => 'BU03639997', 'GeregistreerdeMisdrijven_1' => 0],
            ]]);
        });

        $this->artisan('crime:import', ['--year' => 2025, '--compute' => true])->assertSuccessful();

        $scoreA = CrimeScore::where('neighbourhood_id', $a->id)->where('category', 'robbery')->first();
        $scoreB = CrimeScore::where('neighbourhood_id', $b->id)->where('category', 'robbery')->first();
        $scoreW = CrimeScore::where('neighbourhood_id', $water->id)->where('category', 'robbery')->first();

        $this->assertSame(7, $scoreA->count);                 // 1.4.6 (5) + 1.4.7 (1) + 1.2.4 (1)
        $this->assertSame(7.0, $scoreA->rate_per_1000);
        $this->assertSame(3.5, $scoreB->rate_per_1000);
        $this->assertSame(100, $scoreA->score);
        $this->assertSame(0, $scoreB->score);
        $this->assertNull($scoreW->score);
    }
}
