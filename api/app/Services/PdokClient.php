<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Haalt buurtgrenzen op uit de CBS Wijk- en Buurtkaart via de PDOK WFS 2.0-service.
 * Endpoint: https://service.pdok.nl/cbs/wijkenbuurten/{jaar}/wfs/v1_0
 * Filtering gebeurt met een FES 2.0 XML-filter (CQL_FILTER wordt door PDOK genegeerd).
 */
class PdokClient
{
    public function __construct(
        private readonly string $wfsUrl,
        private readonly int $pageSize = 1000,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('veiligonderweg.pdok.wfs_url'), (int) config('veiligonderweg.pdok.page_size'));
    }

    /**
     * Geeft alle buurt-features (GeoJSON, WGS84) van een gemeente terug.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchNeighbourhoods(string $municipalityCode): array
    {
        $filter = '<Filter xmlns="http://www.opengis.net/fes/2.0"><PropertyIsEqualTo>'
            .'<ValueReference>gemeentecode</ValueReference><Literal>'.$municipalityCode.'</Literal>'
            .'</PropertyIsEqualTo></Filter>';

        $features = [];
        $startIndex = 0;

        do {
            $response = Http::timeout(180)->retry(3, 3000)->get($this->wfsUrl, [
                'request' => 'GetFeature',
                'service' => 'WFS',
                'version' => '2.0.0',
                'typeNames' => 'wijkenbuurten:buurten',
                'outputFormat' => 'json',
                'srsName' => 'EPSG:4326',
                'count' => $this->pageSize,
                'startIndex' => $startIndex,
                'FILTER' => $filter,
            ]);

            if (! $response->ok()) {
                throw new RuntimeException('PDOK WFS gaf status '.$response->status());
            }

            $page = $response->json('features') ?? [];
            $features = array_merge($features, $page);
            $startIndex += $this->pageSize;
        } while (count($page) === $this->pageSize);

        return $features;
    }
}
