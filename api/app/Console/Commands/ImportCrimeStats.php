<?php

namespace App\Console\Commands;

use App\Models\CrimeCount;
use App\Models\Neighbourhood;
use App\Services\CbsOdataClient;
use Illuminate\Console\Command;
use Throwable;

class ImportCrimeStats extends Command
{
    protected $signature = 'crime:import
                            {--municipality= : CBS gemeentecode, bijv. GM0363}
                            {--year= : Jaar (jaarcijfers), standaard uit config}
                            {--compute : Direct daarna de scores berekenen}';

    protected $description = 'Importeert geregistreerde misdrijven per buurt (politie OData, tabel 47018NED)';

    public function handle(CbsOdataClient $client): int
    {
        $municipality = $this->option('municipality') ?: config('veiligonderweg.default_municipality');
        $year = (int) ($this->option('year') ?: config('veiligonderweg.cbs.default_year'));

        $ids = Neighbourhood::where('municipality_code', $municipality)->pluck('id', 'code');
        if ($ids->isEmpty()) {
            $this->error("Geen buurten voor {$municipality}. Draai eerst: php artisan geo:import-neighbourhoods --municipality={$municipality}");

            return self::FAILURE;
        }

        $codes = CbsOdataClient::codesFromConfig();
        $this->info("Misdrijven {$year} ophalen voor {$municipality}: ".count($codes).' delicttypen ...');

        $total = 0;
        $failed = [];
        foreach ($codes as $code) {
            try {
                $counts = $client->fetchCounts($municipality, $year, $code);
            } catch (Throwable $e) {
                $this->warn("  {$code}: mislukt ({$e->getMessage()})");
                $failed[] = $code;

                continue;
            }

            $rows = [];
            foreach ($counts as $buurt => $count) {
                if (! isset($ids[$buurt])) {
                    continue;
                }
                $rows[] = [
                    'neighbourhood_id' => $ids[$buurt],
                    'year' => $year,
                    'crime_type_code' => $code,
                    'count' => $count,
                    'imported_at' => now(),
                ];
            }
            CrimeCount::upsert($rows, ['neighbourhood_id', 'year', 'crime_type_code'], ['count', 'imported_at']);
            $total += count($rows);
            $this->line("  {$code}: ".count($rows).' buurten');
        }

        $this->info("Klaar: {$total} rijen geimporteerd.");
        if ($failed !== []) {
            $this->warn('Mislukt (opnieuw draaien): '.implode(', ', $failed));
        }

        if ($this->option('compute')) {
            $this->call('crime:compute-scores', ['--year' => $year]);
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
