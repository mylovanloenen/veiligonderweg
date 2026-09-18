<?php

namespace Tests\Unit;

use App\Services\ScoreCalculator;
use PHPUnit\Framework\TestCase;

class ScoreCalculatorTest extends TestCase
{
    public function test_normalises_per_1000_inhabitants_and_ranks_as_percentile(): void
    {
        $rows = [
            ['id' => 1, 'population' => 1000, 'eligible' => true, 'count' => 10],  // 10 per 1000
            ['id' => 2, 'population' => 500, 'eligible' => true, 'count' => 10],   // 20 per 1000
            ['id' => 3, 'population' => 2000, 'eligible' => true, 'count' => 10],  // 5 per 1000
        ];

        $result = collect((new ScoreCalculator)->calculate($rows))->keyBy('id');

        $this->assertSame(10.0, $result[1]['rate_per_1000']);
        $this->assertSame(20.0, $result[2]['rate_per_1000']);
        $this->assertSame(5.0, $result[3]['rate_per_1000']);

        $this->assertSame(0, $result[3]['score']);
        $this->assertSame(50, $result[1]['score']);
        $this->assertSame(100, $result[2]['score']);

        $this->assertSame(1, $result[3]['class']);
        $this->assertSame(3, $result[1]['class']);
        $this->assertSame(5, $result[2]['class']);
    }

    public function test_ineligible_neighbourhoods_get_no_score_but_keep_their_count(): void
    {
        $rows = [
            ['id' => 1, 'population' => 1000, 'eligible' => true, 'count' => 3],
            ['id' => 2, 'population' => null, 'eligible' => false, 'count' => 99],
            ['id' => 3, 'population' => 10, 'eligible' => false, 'count' => 1],
        ];

        $result = collect((new ScoreCalculator)->calculate($rows))->keyBy('id');

        $this->assertNull($result[2]['rate_per_1000']);
        $this->assertNull($result[2]['score']);
        $this->assertNull($result[2]['class']);
        $this->assertSame(99, $result[2]['count']);
        $this->assertNull($result[3]['score']);
        $this->assertSame(0, $result[1]['score']); // enige in aanmerking komende buurt
    }

    public function test_class_boundaries(): void
    {
        $this->assertSame(1, ScoreCalculator::classFor(0));
        $this->assertSame(1, ScoreCalculator::classFor(19));
        $this->assertSame(2, ScoreCalculator::classFor(20));
        $this->assertSame(4, ScoreCalculator::classFor(79));
        $this->assertSame(5, ScoreCalculator::classFor(80));
        $this->assertSame(5, ScoreCalculator::classFor(100));
    }
}
