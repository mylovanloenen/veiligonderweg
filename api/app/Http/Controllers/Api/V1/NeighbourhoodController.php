<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CrimeScore;
use App\Models\Neighbourhood;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NeighbourhoodController extends Controller
{
    /**
     * GeoJSON FeatureCollection van buurten met score voor een categorie.
     * Query: bbox=minLng,minLat,maxLng,maxLat (optioneel), category (standaard total), year (standaard laatste).
     */
    public function index(Request $request): JsonResponse
    {
        $categories = array_keys(config('veiligonderweg.crime_categories'));
        $data = $request->validate([
            'bbox' => ['nullable', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/'],
            'category' => ['nullable', Rule::in($categories)],
            'year' => ['nullable', 'integer'],
            'municipality' => ['nullable', 'string', 'max:10'],
        ]);

        $category = $data['category'] ?? 'total';
        $year = (int) ($data['year'] ?? CrimeScore::max('year') ?? config('veiligonderweg.cbs.default_year'));

        $query = Neighbourhood::query()
            ->leftJoin('crime_scores', function ($join) use ($category, $year) {
                $join->on('crime_scores.neighbourhood_id', '=', 'neighbourhoods.id')
                    ->where('crime_scores.category', $category)
                    ->where('crime_scores.year', $year);
            })
            ->select([
                'neighbourhoods.id', 'neighbourhoods.code', 'neighbourhoods.name', 'neighbourhoods.district_code',
                'neighbourhoods.municipality_code', 'neighbourhoods.population', 'neighbourhoods.is_water',
                'crime_scores.count', 'crime_scores.rate_per_1000', 'crime_scores.score', 'crime_scores.class',
            ])
            // Vereenvoudigde geometrie (~10 m) houdt de payload voor 500+ buurten klein.
            ->selectRaw('ST_AsGeoJSON(ST_SimplifyPreserveTopology(neighbourhoods.geom, 0.0001), 6) AS geometry')
            ->whereNotNull('neighbourhoods.geom');

        if (! empty($data['municipality'])) {
            $query->where('neighbourhoods.municipality_code', $data['municipality']);
        }
        if (! empty($data['bbox'])) {
            [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', explode(',', $data['bbox']));
            $query->inBbox($minLng, $minLat, $maxLng, $maxLat);
        }

        $features = $query->get()->map(fn ($n) => [
            'type' => 'Feature',
            'id' => $n->code,
            'geometry' => json_decode($n->geometry, true),
            'properties' => [
                'code' => $n->code,
                'name' => $n->name,
                'district_code' => $n->district_code,
                'population' => $n->population,
                'is_water' => (bool) $n->is_water,
                'category' => $category,
                'year' => $year,
                'count' => $n->count === null ? null : (int) $n->count,
                'rate_per_1000' => $n->rate_per_1000 === null ? null : (float) $n->rate_per_1000,
                'score' => $n->score === null ? null : (int) $n->score,
                'class' => $n->class === null ? null : (int) $n->class,
            ],
        ]);

        return response()->json([
            'type' => 'FeatureCollection',
            'meta' => ['category' => $category, 'year' => $year, 'count' => $features->count()],
            'features' => $features,
        ]);
    }

    /** Alle categorieen voor een buurt. */
    public function show(string $code): JsonResponse
    {
        $neighbourhood = Neighbourhood::where('code', $code)->firstOrFail();
        $year = CrimeScore::where('neighbourhood_id', $neighbourhood->id)->max('year');

        $scores = CrimeScore::where('neighbourhood_id', $neighbourhood->id)
            ->where('year', $year)
            ->get()
            ->keyBy('category')
            ->map(fn ($s) => [
                'count' => $s->count, 'rate_per_1000' => $s->rate_per_1000, 'score' => $s->score, 'class' => $s->class,
            ]);

        return response()->json([
            'code' => $neighbourhood->code,
            'name' => $neighbourhood->name,
            'district_code' => $neighbourhood->district_code,
            'municipality_code' => $neighbourhood->municipality_code,
            'municipality_name' => $neighbourhood->municipality_name,
            'population' => $neighbourhood->population,
            'is_water' => $neighbourhood->is_water,
            'year' => $year,
            'scores' => $scores,
        ]);
    }

    /** Beschikbare scorecategorieen met label. */
    public function categories(): JsonResponse
    {
        $out = [];
        foreach (config('veiligonderweg.crime_categories') as $key => $def) {
            $out[] = ['key' => $key, 'label' => $def['label'], 'codes' => $def['codes']];
        }

        return response()->json(['data' => $out]);
    }
}
