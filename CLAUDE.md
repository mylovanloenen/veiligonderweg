# VeiligOnderweg

MVP van een "Flitsmeister voor onveilige situaties": een kaart met (1) een statistische laag per buurt op basis van
politie-open-data en (2) live meldingen van gebruikers die automatisch verlopen.

## Stack

- `api/` — Laravel 13 (PHP 8.4) als pure REST-API (JSON, geen Blade). Auth via Laravel Sanctum-tokens.
- `db` — PostgreSQL 16 + PostGIS 3.4 (image `postgis/postgis:16-3.4`).
- `web/` — React 19 + Vite + TypeScript, Leaflet/react-leaflet met OpenStreetMap-tiles.
- Alles draait via `docker-compose.yml` (services `db`, `api`, `scheduler`, `web`). PHP/Composer staan niet lokaal; gebruik de container.
- Later: React Native (Expo) app op dezelfde API. Houd daarom alle logica in de API, de frontend is een dunne client
  (`web/src/api/client.ts` is framework-loos en herbruikbaar).

## Starten

```bash
docker compose up -d --build           # db, api (http://localhost:8000), scheduler en web (http://localhost:5173)
docker compose run --rm api php artisan migrate --seed
docker compose run --rm api php artisan geo:import-neighbourhoods --municipality=GM0363
docker compose run --rm api php artisan crime:import --municipality=GM0363 --year=2025 --compute
```

De `web`-service draait Vite in een node-container met een eigen `node_modules`-volume (Linux-binaries), dus lokaal
`npm install` is niet nodig. Wil je toch lokaal draaien: `cd web && npm install && npm run dev`. De `scheduler`-service
draait `php artisan schedule:work` (o.a. `incidents:anonymise` elk uur).

Tests (draaien tegen de PostGIS-testdatabase `veiligonderweg_test`, aangemaakt door `docker/db/10-create-test-db.sh`):

```bash
docker compose run --rm api php artisan test
```

Nieuwe migraties/commands altijd via de container: `docker compose run --rm api php artisan ...`.

## Databronnen (geverifieerd op 2026-09-18)

### Politie: geregistreerde misdrijven per buurt

- Catalogus "Politie" op `https://dataderden.cbs.nl/ODataCatalog/Tables?$filter=Catalog eq 'Politie'&$format=json`
- Gebruikte tabel: **47018NED** "Geregistreerde misdrijven; soort misdrijf, wijk, buurt, jaarcijfers" (2012–2025, 2025 definitief).
  Alternatief met maandcijfers: 47022NED (zelfde structuur, `Perioden` = `2025MM06`).
- Endpoint: `https://dataderden.cbs.nl/ODataApi/odata/47018NED/TypedDataSet`
- Kolommen: `WijkenEnBuurten` (BU0363AA01), `SoortMisdrijf` (bijv. `1.4.6 ` met **spatie-padding tot 6 tekens**),
  `Perioden` (`2025JJ00`), `GeregistreerdeMisdrijven_1`.
- Werkende filter: `startswith(WijkenEnBuurten,'BU0363') and Perioden eq '2025JJ00' and SoortMisdrijf eq '1.4.6 '`.
- Eigenaardigheden: server is traag en geeft soms time-outs (client heeft retries), `$count` en `$skip` bleken
  onbetrouwbaar. Daarom haalt `crime:import` per delicttype een aparte query op (~517 rijen voor Amsterdam).
- Buurtindeling is die van 1 januari 2025, ook voor oudere jaren.
- Delictcodes staan in `SoortMisdrijf`-lijst: `.../47018NED/SoortMisdrijf`. De mapping naar onze categorieën staat in
  `api/config/veiligonderweg.php` (`crime_categories`).

### CBS Wijk- en Buurtkaart (buurtgrenzen + inwonertal) via PDOK

- WFS 2.0: `https://service.pdok.nl/cbs/wijkenbuurten/2025/wfs/v1_0`
- `typeNames=wijkenbuurten:buurten`, `outputFormat=json`, `srsName=EPSG:4326`
- Filteren op gemeente werkt alleen met een FES 2.0 XML-`FILTER` op `gemeentecode` (`CQL_FILTER` wordt genegeerd).
  Er is geen OGC API Features-endpoint voor deze dataset (404).
- Relevante properties: `buurtcode`, `buurtnaam`, `wijkcode`, `gemeentecode`, `gemeentenaam`, `aantalInwoners`
  (`-99997` = geheim/n.v.t. → `null`), `water` (`JA`/`NEE`). Amsterdam: 519 buurten, waarvan 2 water.
- Inwonertal komt dus uit dezelfde 2025-indeling als de politiecijfers.

## Risicoscore

Per buurt en categorie: `rate = aantal / inwoners * 1000` over het jaar, daarna een percentielrang (0–100) binnen de
gemeente en een legendaklasse 1–5 (`ScoreCalculator`). Buurten met < 50 inwoners, geheim inwonertal of water krijgen
`null` ("onvoldoende data"). Ruwe aantallen per delictcode staan in `crime_counts`, zodat `crime:compute-scores` opnieuw
kan draaien zonder download.

Bekende beperking: buurten met veel bezoekers en weinig bewoners (Centrum, uitgaansgebieden) scoren per inwoner extreem
hoog. De percentielrang dempt dat, maar lost het niet op.

## Inhoud en privacy (harde eisen)

- Meldingen gaan over situaties, nooit over personen. Geen velden voor signalement/uiterlijk/afkomst.
- `ContentFilter` weigert (niet strippen) tekst met telefoonnummers, e-mail, links, kentekens, postcode+huisnummer,
  @handles, persoonsbeschrijvende termen en scheldwoorden. Lijsten in `config/veiligonderweg.php`.
- Geen locatiegeschiedenis: alleen de meldlocatie bij een melding, gekoppeld aan `user_id`. `incidents:anonymise`
  (scheduler, elk uur) leegt `user_id` en tekst 7 dagen na verlopen en verwijdert meldingen na 30 dagen.
- Frontend toont altijd "Direct gevaar? Bel 112" en tekent meldingen prominent bovenop een ingetogen choropleth
  (fillOpacity 0.35) om stigmatisering van wijken te beperken.

## Meldingen: levenscyclus

- TTL per categorie (`incident_categories.ttl_minutes`, seed uit config). Standaard 120 min, slechte verlichting 720.
- Bevestiging (`POST /incidents/{id}/confirm`) verlengt de vervaltijd met 30 min tot max 2× TTL.
- Weerlegging: bij ≥ 3 weerleggingen die de bevestigingen overtreffen wordt de melding verborgen (`hidden_at`).
- Eén stem per gebruiker per melding, eigen melding niet beoordelen. Rate limit: 5 meldingen/uur/gebruiker.

## API (`/api/v1`)

| Methode | Pad | Auth | Doel |
|---|---|---|---|
| GET | `/neighbourhoods?category=&municipality=&bbox=&year=` | – | GeoJSON choropleth |
| GET | `/neighbourhoods/categories` | – | scorecategorieën |
| GET | `/neighbourhoods/{code}` | – | alle scores van één buurt |
| GET | `/categories` | – | meldcategorieën |
| GET | `/incidents?lat=&lng=&radius=` of `?bbox=` | – | actieve meldingen (ST_DWithin / bbox) |
| POST | `/incidents` | Sanctum | melding plaatsen (throttle `incidents`) |
| POST | `/incidents/{id}/confirm` / `dispute` | Sanctum | beoordelen |
| POST | `/auth/register`, `/auth/login`, `/auth/logout`; GET `/auth/me` | | accounts |

## Afspraken

- Kleine stappen, elke fase een commit met duidelijke message. Feature-tests voor geo-queries en verloop van meldingen.
- Geen endpoints verzinnen: nieuwe databronnen eerst verifiëren met `curl` en hier documenteren.
- Frontend-teksten in het Nederlands. Code en identifiers in het Engels.
