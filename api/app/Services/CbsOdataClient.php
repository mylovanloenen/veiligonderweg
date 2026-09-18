<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Leest geregistreerde misdrijven per buurt uit de politie-OData op dataderden.cbs.nl.
 * Tabel 47018NED: "Geregistreerde misdrijven; soort misdrijf, wijk, buurt, jaarcijfers".
 *
 * De server is traag en geeft af en toe time-outs; daarom per delicttype een aparte,
 * kleine query (max ~520 rijen voor Amsterdam) met retries. $skip-paging bleek onbetrouwbaar.
 */
class CbsOdataClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $table,
        private readonly int $pageSize = 10000,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('veiligonderweg.cbs.base_url'),
            config('veiligonderweg.cbs.yearly_table'),
            (int) config('veiligonderweg.cbs.page_size'),
        );
    }

    /**
     * Aantallen per buurt voor een delictcode en jaar binnen een gemeente.
     *
     * @return array<string, int> buurtcode => aantal
     */
    public function fetchCounts(string $municipalityCode, int $year, string $crimeTypeCode): array
    {
        // GM0363 -> buurten beginnen met BU0363. Keys van SoortMisdrijf zijn uitgevuld tot 6 tekens.
        $prefix = 'BU'.substr($municipalityCode, 2);
        $typeKey = str_pad($crimeTypeCode, 6, ' ');
        $period = $year.'JJ00';

        $filter = sprintf(
            "startswith(WijkenEnBuurten,'%s') and Perioden eq '%s' and SoortMisdrijf eq '%s'",
            $prefix, $period, $typeKey
        );

        $url = sprintf('%s/%s/TypedDataSet', rtrim($this->baseUrl, '/'), $this->table);

        $response = Http::timeout(180)->retry(4, 5000)->get($url, [
            '$filter' => $filter,
            '$select' => 'WijkenEnBuurten,GeregistreerdeMisdrijven_1',
            '$top' => $this->pageSize,
        ]);

        if (! $response->ok()) {
            throw new RuntimeException('CBS OData gaf status '.$response->status());
        }

        $rows = $response->json('value');
        if (! is_array($rows)) {
            throw new RuntimeException('CBS OData gaf een onverwacht antwoord (geen value-array).');
        }

        $counts = [];
        foreach ($rows as $row) {
            $code = trim((string) $row['WijkenEnBuurten']);
            $counts[$code] = (int) ($row['GeregistreerdeMisdrijven_1'] ?? 0);
        }

        return $counts;
    }

    /** Alle delictcodes die ergens in de categorie-mapping voorkomen. */
    public static function codesFromConfig(): array
    {
        $codes = [];
        foreach (config('veiligonderweg.crime_categories') as $category) {
            $codes = array_merge($codes, $category['codes']);
        }

        return array_values(array_unique($codes));
    }
}
