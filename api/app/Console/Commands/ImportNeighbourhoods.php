<?php

namespace App\Console\Commands;

use App\Models\Neighbourhood;
use App\Services\PdokClient;
use Illuminate\Console\Command;

class ImportNeighbourhoods extends Command
{
    protected $signature = 'geo:import-neighbourhoods
                            {--municipality= : CBS gemeentecode, bijv. GM0363 (Amsterdam)}';

    protected $description = 'Importeert buurtgrenzen en inwonertallen (CBS Wijk- en Buurtkaart via PDOK WFS) in PostGIS';

    public function handle(PdokClient $client): int
    {
        $municipality = $this->option('municipality') ?: config('veiligonderweg.default_municipality');
        $year = (int) config('veiligonderweg.pdok.year');

        $this->info("Buurten ophalen voor {$municipality} (kaart {$year}) ...");
        $features = $client->fetchNeighbourhoods($municipality);
        $this->info(count($features).' buurten ontvangen.');

        $bar = $this->output->createProgressBar(count($features));
        $created = 0;
        $updated = 0;

        foreach ($features as $feature) {
            $p = $feature['properties'];
            $population = (int) $p['aantalInwoners'];

            $neighbourhood = Neighbourhood::updateOrCreate(
                ['code' => $p['buurtcode']],
                [
                    'name' => $p['buurtnaam'],
                    'district_code' => $p['wijkcode'] ?? null,
                    'municipality_code' => $p['gemeentecode'],
                    'municipality_name' => $p['gemeentenaam'] ?? null,
                    'population' => $population < 0 ? null : $population, // -99997 = geheim/n.v.t.
                    'is_water' => ($p['water'] ?? 'NEE') === 'JA',
                    'source_year' => $year,
                ]
            );
            $neighbourhood->wasRecentlyCreated ? $created++ : $updated++;
            $neighbourhood->setGeometryFromGeoJson($feature['geometry']);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Klaar: {$created} nieuw, {$updated} bijgewerkt.");

        return self::SUCCESS;
    }
}
