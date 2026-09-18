<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentVote;
use App\Services\ContentFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IncidentController extends Controller
{
    /** Vaste categorielijst. */
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => IncidentCategory::orderBy('id')->get(['slug', 'name', 'ttl_minutes']),
        ]);
    }

    /**
     * Actieve meldingen binnen X meter van een coordinaat (ST_DWithin) of binnen een bbox.
     * Query: lat, lng, radius (m)  OF  bbox=minLng,minLat,maxLng,maxLat
     */
    public function index(Request $request): JsonResponse
    {
        $maxRadius = (int) config('veiligonderweg.incidents.max_radius_m');
        $data = $request->validate([
            'lat' => ['required_without:bbox', 'numeric', 'between:-90,90'],
            'lng' => ['required_without:bbox', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'min:50', "max:{$maxRadius}"],
            'bbox' => ['required_without_all:lat,lng', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/'],
        ]);

        $query = Incident::query()->visible()->withCoordinates()->with('category');

        if (isset($data['lat'], $data['lng'])) {
            $radius = (int) ($data['radius'] ?? config('veiligonderweg.incidents.default_radius_m'));
            $query->withinMeters((float) $data['lat'], (float) $data['lng'], $radius);
        } else {
            [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', explode(',', $data['bbox']));
            $query->inBbox($minLng, $minLat, $maxLng, $maxLat)->orderByDesc('created_at');
        }

        $incidents = $query->limit(500)->get();

        return response()->json(['data' => $incidents->map(fn ($i) => $this->serialize($i))]);
    }

    public function store(Request $request, ContentFilter $filter): JsonResponse
    {
        $maxLen = (int) config('veiligonderweg.incidents.max_description_length');
        $data = $request->validate([
            'category' => ['required', 'string', 'exists:incident_categories,slug'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', "max:{$maxLen}"],
        ]);

        $violations = $filter->violations($data['description'] ?? null);
        if ($violations !== []) {
            throw ValidationException::withMessages([
                'description' => ['Tekst niet toegestaan: '.implode('; ', $violations).'.'],
            ]);
        }

        $category = IncidentCategory::where('slug', $data['category'])->firstOrFail();

        $incident = Incident::createAt([
            'incident_category_id' => $category->id,
            'user_id' => $request->user()->id,
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'expires_at' => now()->addMinutes($category->ttl_minutes),
        ], (float) $data['lat'], (float) $data['lng']);

        $incident = Incident::withCoordinates()->with('category')->findOrFail($incident->id);

        return response()->json(['data' => $this->serialize($incident)], 201);
    }

    public function confirm(Request $request, Incident $incident): JsonResponse
    {
        return $this->vote($request, $incident, IncidentVote::CONFIRM);
    }

    public function dispute(Request $request, Incident $incident): JsonResponse
    {
        return $this->vote($request, $incident, IncidentVote::DISPUTE);
    }

    private function vote(Request $request, Incident $incident, string $type): JsonResponse
    {
        if ($incident->hidden_at !== null || $incident->expires_at->isPast()) {
            return response()->json(['message' => 'Deze melding is niet meer actief.'], 410);
        }
        if ($incident->user_id === $request->user()->id) {
            return response()->json(['message' => 'Je kunt je eigen melding niet beoordelen.'], 422);
        }
        if ($incident->votes()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'Je hebt deze melding al beoordeeld.'], 409);
        }

        IncidentVote::create(['incident_id' => $incident->id, 'user_id' => $request->user()->id, 'type' => $type]);
        $type === IncidentVote::CONFIRM ? $incident->applyConfirmation() : $incident->applyDispute();

        $incident = Incident::withCoordinates()->with('category')->findOrFail($incident->id);

        return response()->json(['data' => $this->serialize($incident)]);
    }

    private function serialize(Incident $i): array
    {
        return [
            'id' => $i->id,
            'category' => ['slug' => $i->category->slug, 'name' => $i->category->name],
            'lat' => (float) $i->lat,
            'lng' => (float) $i->lng,
            'description' => $i->description,
            'confirmations' => $i->confirmations,
            'disputes' => $i->disputes,
            'created_at' => $i->created_at?->toIso8601String(),
            'expires_at' => $i->expires_at?->toIso8601String(),
            'hidden' => $i->hidden_at !== null,
            'distance_m' => isset($i->distance_m) ? round((float) $i->distance_m) : null,
        ];
    }
}
