import { useEffect, useMemo } from 'react'
import L from 'leaflet'
import { GeoJSON, MapContainer, Marker, Popup, TileLayer, useMap, useMapEvents } from 'react-leaflet'
import type { Incident, NeighbourhoodCollection, NeighbourhoodProps } from '../api/client'
import { CLASS_COLORS, CLASS_LABELS, INCIDENT_COLORS, INCIDENT_ICONS, isRecent, minutesUntil, timeAgo } from '../format'

export const AMSTERDAM: [number, number] = [52.3676, 4.9041]

interface Props {
  neighbourhoods: NeighbourhoodCollection | null
  showStats: boolean
  incidents: Incident[]
  onBoundsChange: (b: L.LatLngBounds) => void
  pickingLocation: boolean
  pickedLocation: { lat: number; lng: number } | null
  onPickLocation: (lat: number, lng: number) => void
  flyTo: { lat: number; lng: number; key: number } | null
  onConfirm: (id: number) => void
  onDispute: (id: number) => void
  canVote: boolean
  votedIds: Set<number>
}

function BoundsWatcher({ onBoundsChange }: { onBoundsChange: (b: L.LatLngBounds) => void }) {
  const map = useMapEvents({
    moveend: () => onBoundsChange(map.getBounds()),
  })
  useEffect(() => {
    // Direct na mount heeft de container soms nog geen afmeting (bbox van 0 m2); wacht een frame.
    const id = requestAnimationFrame(() => {
      map.invalidateSize()
      const b = map.getBounds()
      if (b.getEast() > b.getWest() && b.getNorth() > b.getSouth()) onBoundsChange(b)
    })
    return () => cancelAnimationFrame(id)
  }, [map, onBoundsChange])
  return null
}

function ClickPicker({ active, onPick }: { active: boolean; onPick: (lat: number, lng: number) => void }) {
  const map = useMapEvents({
    click: (e) => {
      if (active) onPick(e.latlng.lat, e.latlng.lng)
    },
  })
  useEffect(() => {
    map.getContainer().style.cursor = active ? 'crosshair' : ''
  }, [map, active])
  return null
}

function FlyTo({ target }: { target: Props['flyTo'] }) {
  const map = useMap()
  useEffect(() => {
    if (target) map.flyTo([target.lat, target.lng], Math.max(map.getZoom(), 15), { duration: 0.8 })
  }, [map, target])
  return null
}

const pickIcon = L.divIcon({ className: 'pick-marker', html: '<div class="pick-marker__pin"></div>', iconSize: [24, 24], iconAnchor: [12, 24] })

function incidentIcon(incident: Incident): L.DivIcon {
  const color = INCIDENT_COLORS[incident.category.slug] ?? '#4b5563'
  const recent = isRecent(incident.created_at)
  return L.divIcon({
    className: `incident-marker ${recent ? 'incident-marker--recent' : ''}`,
    html: `<div class="incident-marker__ring" style="--c:${color}"></div><div class="incident-marker__dot" style="background:${color}">${INCIDENT_ICONS[incident.category.slug] ?? '❔'}</div>`,
    iconSize: [34, 34],
    iconAnchor: [17, 17],
    popupAnchor: [0, -16],
  })
}

export function MapView(props: Props) {
  const { neighbourhoods, showStats, incidents, onBoundsChange, pickingLocation, pickedLocation, onPickLocation, flyTo, onConfirm, onDispute, canVote, votedIds } = props

  // Key forceert een nieuwe GeoJSON-laag bij andere data/categorie (react-leaflet muteert anders niet).
  const geoKey = useMemo(
    () => (neighbourhoods ? `${neighbourhoods.meta.category}-${neighbourhoods.meta.year}-${neighbourhoods.features.length}` : 'none'),
    [neighbourhoods],
  )

  const style = (feature?: GeoJSON.Feature): L.PathOptions => {
    const p = feature?.properties as NeighbourhoodProps | undefined
    if (!p || p.is_water) return { fillOpacity: 0, opacity: 0 }
    if (p.class == null) return { color: '#94a3b8', weight: 0.6, fillColor: '#cbd5e1', fillOpacity: 0.12, dashArray: '2 3' }
    // Bewust lage dekking: recente meldingen moeten dominanter zijn dan de statistische kleur.
    return { color: '#64748b', weight: 0.6, fillColor: CLASS_COLORS[p.class], fillOpacity: 0.35 }
  }

  const onEachFeature = (feature: GeoJSON.Feature, layer: L.Layer) => {
    const p = feature.properties as NeighbourhoodProps
    if (p.is_water) return
    const scoreText = p.class == null ? 'Onvoldoende data (te weinig inwoners)' : `${CLASS_LABELS[p.class]} (score ${p.score}/100)`
    layer.bindPopup(
      `<div class="nb-popup"><strong>${p.name}</strong><br/><span class="muted">${p.code}</span><br/>` +
        `${scoreText}<br/>` +
        `${p.count ?? '–'} geregistreerd in ${p.year}` +
        (p.rate_per_1000 != null ? ` · ${p.rate_per_1000.toFixed(1)} per 1.000 inw.` : '') +
        (p.population != null ? `<br/><span class="muted">${p.population.toLocaleString('nl-NL')} inwoners</span>` : '') +
        `</div>`,
    )
  }

  return (
    <MapContainer center={AMSTERDAM} zoom={12} className="map" zoomControl={false} preferCanvas>
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> · Misdrijfcijfers: Politie/CBS · Buurten: CBS/PDOK'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />
      <BoundsWatcher onBoundsChange={onBoundsChange} />
      <ClickPicker active={pickingLocation} onPick={onPickLocation} />
      <FlyTo target={flyTo} />

      {showStats && neighbourhoods && (
        <GeoJSON key={geoKey} data={neighbourhoods as unknown as GeoJSON.FeatureCollection} style={style} onEachFeature={onEachFeature} />
      )}

      {incidents.map((incident) => (
        <Marker key={incident.id} position={[incident.lat, incident.lng]} icon={incidentIcon(incident)} zIndexOffset={1000}>
          <Popup>
            <div className="inc-popup">
              <div className="inc-popup__title" style={{ color: INCIDENT_COLORS[incident.category.slug] }}>
                {INCIDENT_ICONS[incident.category.slug]} {incident.category.name}
              </div>
              <div className="muted">
                {timeAgo(incident.created_at)} · verloopt over {minutesUntil(incident.expires_at)} min
              </div>
              {incident.description && <p>{incident.description}</p>}
              <div className="inc-popup__votes">
                <button className="btn btn--small" disabled={!canVote || votedIds.has(incident.id)} onClick={() => onConfirm(incident.id)}>
                  👍 Klopt ({incident.confirmations})
                </button>
                <button className="btn btn--small" disabled={!canVote || votedIds.has(incident.id)} onClick={() => onDispute(incident.id)}>
                  👎 Klopt niet ({incident.disputes})
                </button>
              </div>
              {!canVote && <div className="muted small">Log in om te beoordelen.</div>}
            </div>
          </Popup>
        </Marker>
      ))}

      {pickedLocation && <Marker position={[pickedLocation.lat, pickedLocation.lng]} icon={pickIcon} zIndexOffset={2000} />}
    </MapContainer>
  )
}
