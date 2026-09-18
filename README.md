# VeiligOnderweg

Kaart met statistische veiligheid per buurt (politie-open-data) en live meldingen van onveilige situaties.
Start met Amsterdam als testgebied. **Direct gevaar? Bel 112.**

## Online demo

Statische demo op GitHub Pages (snapshot van Amsterdam, meldingen worden niet opgeslagen):
**https://mylovanloenen.github.io/veiligonderweg/**

De demo wordt automatisch gebouwd bij elke push naar `main` (`.github/workflows/pages.yml`). Snapshot verversen:
`docker compose up -d && ./scripts/export-demo.sh` en de wijzigingen in `web/public/demo/` committen.

## Installatie (5 commando's)

Vereist: alleen Docker Desktop.

```bash
git clone <repo-url> veiligonderweg && cd veiligonderweg
docker compose up -d --build
docker compose run --rm api php artisan migrate --seed
docker compose run --rm api php artisan geo:import-neighbourhoods
docker compose run --rm api php artisan crime:import --compute
```

- API: http://localhost:8000 (healthcheck `/up`)
- Web: http://localhost:5173 (Vite in Docker, hot reload werkt via de bind mount)
- Scheduler: container `scheduler` draait `php artisan schedule:work` (anonimisering elk uur)
- Demo-accounts: `demo@veiligonderweg.local` en `buur@veiligonderweg.local`, wachtwoord `demo1234` (of maak zelf een account)

De import haalt 519 Amsterdamse buurten op bij PDOK en 17 delicttypen bij de politie-OData (duurt ~1 minuut, de
CBS-server is traag). Draai `crime:import --compute` periodiek om de cijfers te verversen.

## Tests

```bash
docker compose run --rm api php artisan test
```

## Structuur

- `api/` Laravel REST-API (PostgreSQL + PostGIS)
- `web/` React + Vite + Leaflet frontend
- `docker/` Dockerfiles en database-initialisatie
- `CLAUDE.md` projectafspraken, databronnen en detailuitleg
