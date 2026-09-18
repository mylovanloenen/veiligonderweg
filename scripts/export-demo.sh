#!/usr/bin/env bash
# Exporteert een statische snapshot van de API naar web/public/demo/ voor de GitHub Pages-demo.
# Vereist een draaiende API (docker compose up -d) met geimporteerde buurten.
set -euo pipefail
API="${API_URL:-http://localhost:8000}/api/v1"
OUT="$(cd "$(dirname "$0")/.." && pwd)/web/public/demo"
mkdir -p "$OUT"

curl -sf "$API/neighbourhoods/categories" > "$OUT/crime-categories.json"
curl -sf "$API/categories" > "$OUT/categories.json"
for cat in total violence robbery burglary theft nuisance; do
  curl -sf "$API/neighbourhoods?category=$cat&municipality=GM0363" > "$OUT/neighbourhoods-$cat.json"
done
# Meldingen rond Amsterdam plus exportmoment, zodat de demo de tijden kan verschuiven naar 'nu'.
curl -sf "$API/incidents?bbox=4.7,52.25,5.1,52.45" \
  | python3 -c "import sys,json,datetime; d=json.load(sys.stdin); d['exported_at']=datetime.datetime.now(datetime.timezone.utc).isoformat(); print(json.dumps(d))" \
  > "$OUT/incidents.json"

du -sh "$OUT"; ls "$OUT"
