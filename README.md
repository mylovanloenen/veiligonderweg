# VeiligOnderweg

Kaart met statistische veiligheid per buurt (politie-open-data) en live meldingen van onveilige situaties.
Start met Amsterdam als testgebied. **Direct gevaar? Bel 112.**

## Installatie (5 commando's)

Vereist: Docker Desktop en Node 20+.

```bash
docker compose up -d db api
docker compose run --rm api sh -c "php artisan migrate --seed && php artisan geo:import-neighbourhoods && php artisan crime:import --compute"
cd web && npm install && npm run dev
```

- API: http://localhost:8000 (healthcheck `/up`)
- Web: http://localhost:5173
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
