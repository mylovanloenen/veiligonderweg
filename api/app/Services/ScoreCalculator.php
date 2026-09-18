<?php

namespace App\Services;

/**
 * Berekent per buurt een genormaliseerde risicoscore per categorie.
 *
 * rate  = aantal / inwoners * 1000
 * score = percentielrang (0..100) van de rate onder alle in-aanmerking-komende buurten
 * class = legendaklasse 1..5 op basis van de score
 *
 * Buurten zonder (betrouwbaar) inwonertal krijgen rate/score/class = null.
 */
class ScoreCalculator
{
    /**
     * @param  array<int, array{id:int, population:?int, eligible:bool, count:int}>  $rows
     * @return array<int, array{id:int, count:int, rate_per_1000:?float, score:?int, class:?int}>
     */
    public function calculate(array $rows): array
    {
        $rates = [];
        foreach ($rows as $row) {
            $rates[$row['id']] = $row['eligible'] && $row['population'] > 0
                ? $row['count'] / $row['population'] * 1000
                : null;
        }

        $eligible = array_filter($rates, fn ($r) => $r !== null);
        $sorted = array_values($eligible);
        sort($sorted);
        $n = count($sorted);

        $result = [];
        foreach ($rows as $row) {
            $rate = $rates[$row['id']];
            $score = null;
            if ($rate !== null && $n > 0) {
                // Percentielrang: aandeel buurten met een strikt lagere rate.
                $lower = 0;
                foreach ($sorted as $value) {
                    if ($value < $rate) {
                        $lower++;
                    } else {
                        break;
                    }
                }
                $score = $n > 1 ? (int) round($lower / ($n - 1) * 100) : 0;
            }

            $result[] = [
                'id' => $row['id'],
                'count' => $row['count'],
                'rate_per_1000' => $rate === null ? null : round($rate, 3),
                'score' => $score,
                'class' => $score === null ? null : self::classFor($score),
            ];
        }

        return $result;
    }

    public static function classFor(int $score): int
    {
        return match (true) {
            $score < 20 => 1,
            $score < 40 => 2,
            $score < 60 => 3,
            $score < 80 => 4,
            default => 5,
        };
    }
}
