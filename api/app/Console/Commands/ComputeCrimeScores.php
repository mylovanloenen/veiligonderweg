<?php

namespace App\Console\Commands;

use App\Models\CrimeCount;
use App\Models\CrimeScore;
use App\Models\Neighbourhood;
use App\Services\ScoreCalculator;
use Illuminate\Console\Command;

class ComputeCrimeScores extends Command
{
    protected $signature = 'crime:compute-scores {--year= : Jaar, standaard uit config}';

    protected $description = 'Berekent per buurt en categorie de genormaliseerde risicoscore uit de geimporteerde aantallen';

    public function handle(ScoreCalculator $calculator): int
    {
        $year = (int) ($this->option('year') ?: config('veiligonderweg.cbs.default_year'));
        $categories = config('veiligonderweg.crime_categories');

        $counts = CrimeCount::where('year', $year)->get()->groupBy('neighbourhood_id');
        if ($counts->isEmpty()) {
            $this->error("Geen aantallen voor {$year}. Draai eerst: php artisan crime:import --year={$year}");

            return self::FAILURE;
        }

        // Scores worden per gemeente genormaliseerd (percentielrang binnen de gemeente).
        $byMunicipality = Neighbourhood::whereIn('id', $counts->keys())->get()->groupBy('municipality_code');

        foreach ($byMunicipality as $municipality => $neighbourhoods) {
            foreach ($categories as $category => $definition) {
                $rows = $neighbourhoods->map(function (Neighbourhood $n) use ($counts, $definition) {
                    $sum = $counts[$n->id]->whereIn('crime_type_code', $definition['codes'])->sum('count');

                    return ['id' => $n->id, 'population' => $n->population, 'eligible' => $n->isScoreEligible(), 'count' => (int) $sum];
                })->all();

                $upserts = array_map(fn ($r) => [
                    'neighbourhood_id' => $r['id'],
                    'year' => $year,
                    'category' => $category,
                    'count' => $r['count'],
                    'rate_per_1000' => $r['rate_per_1000'],
                    'score' => $r['score'],
                    'class' => $r['class'],
                    'computed_at' => now(),
                ], $calculator->calculate($rows));

                CrimeScore::upsert($upserts, ['neighbourhood_id', 'year', 'category'], ['count', 'rate_per_1000', 'score', 'class', 'computed_at']);
                $this->line("  {$municipality} / {$category}: ".count($upserts).' buurten');
            }
        }

        $this->info('Scores berekend.');

        return self::SUCCESS;
    }
}
