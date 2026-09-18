/**
 * Demo-modus (VITE_DEMO=1): statische snapshot uit /demo/*.json, bedoeld voor GitHub Pages.
 * Er is geen server: meldingen en stemmen bestaan alleen in deze browsersessie.
 */
import type { CrimeCategory, CrimeCategoryKey, Incident, IncidentCategory, NeighbourhoodCollection, User } from './client'

const base = `${import.meta.env.BASE_URL}demo/`

async function load<T>(file: string): Promise<T> {
  const res = await fetch(base + file)
  if (!res.ok) throw new Error(`Demo-bestand ${file} ontbreekt (${res.status})`)
  return res.json() as Promise<T>
}

const DEMO_USER: User = { id: 0, name: 'Demo', email: 'demo@veiligonderweg.local' }
let incidents: Incident[] | null = null
let nextId = 100000

async function loadIncidents(): Promise<Incident[]> {
  if (incidents) return incidents
  const snap = await load<{ data: Incident[]; exported_at: string }>('incidents.json')
  // Verschuif alle tijden zodat de snapshot 'nu' lijkt en meldingen niet verlopen zijn.
  const shift = Date.now() - new Date(snap.exported_at).getTime()
  const move = (iso: string) => new Date(new Date(iso).getTime() + shift).toISOString()
  incidents = snap.data.map((i) => ({ ...i, created_at: move(i.created_at), expires_at: move(i.expires_at) }))
  return incidents
}

const active = (list: Incident[]) => list.filter((i) => !i.hidden && new Date(i.expires_at).getTime() > Date.now())

export const demoApi = {
  crimeCategories: () => load<{ data: CrimeCategory[] }>('crime-categories.json').then((r) => r.data),
  neighbourhoods: (category: CrimeCategoryKey) => load<NeighbourhoodCollection>(`neighbourhoods-${category}.json`),
  incidentCategories: () => load<{ data: IncidentCategory[] }>('categories.json').then((r) => r.data),

  incidentsInBbox: async (minLng: number, minLat: number, maxLng: number, maxLat: number) =>
    active(await loadIncidents()).filter((i) => i.lng >= minLng && i.lng <= maxLng && i.lat >= minLat && i.lat <= maxLat),

  incidentsNear: async (lat: number, lng: number, radius = 1000) =>
    active(await loadIncidents())
      .map((i) => ({ ...i, distance_m: haversine(lat, lng, i.lat, i.lng) }))
      .filter((i) => (i.distance_m ?? 0) <= radius)
      .sort((a, b) => (a.distance_m ?? 0) - (b.distance_m ?? 0)),

  createIncident: async (body: { category: string; lat: number; lng: number; description?: string }) => {
    const cats = await demoApi.incidentCategories()
    const cat = cats.find((c) => c.slug === body.category) ?? cats[0]
    const now = Date.now()
    const incident: Incident = {
      id: nextId++,
      category: { slug: cat.slug, name: cat.name },
      lat: body.lat,
      lng: body.lng,
      description: body.description ?? null,
      confirmations: 0,
      disputes: 0,
      created_at: new Date(now).toISOString(),
      expires_at: new Date(now + cat.ttl_minutes * 60000).toISOString(),
      hidden: false,
      distance_m: null,
    }
    ;(await loadIncidents()).unshift(incident)
    return incident
  },

  confirm: async (id: number) => vote(id, 'confirm'),
  dispute: async (id: number) => vote(id, 'dispute'),

  register: async () => ({ token: 'demo', user: DEMO_USER }),
  login: async () => ({ token: 'demo', user: DEMO_USER }),
  logout: async () => ({ message: 'Uitgelogd.' }),
  me: async () => DEMO_USER,
}

async function vote(id: number, type: 'confirm' | 'dispute'): Promise<Incident> {
  const list = await loadIncidents()
  const i = list.find((x) => x.id === id)
  if (!i) throw new Error('Melding niet gevonden.')
  if (type === 'confirm') {
    i.confirmations++
    i.expires_at = new Date(new Date(i.expires_at).getTime() + 30 * 60000).toISOString()
  } else {
    i.disputes++
    if (i.disputes >= 3 && i.disputes > i.confirmations) i.hidden = true
  }
  return { ...i }
}

function haversine(lat1: number, lng1: number, lat2: number, lng2: number): number {
  const R = 6371000
  const toRad = (d: number) => (d * Math.PI) / 180
  const dLat = toRad(lat2 - lat1)
  const dLng = toRad(lng2 - lng1)
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2
  return 2 * R * Math.asin(Math.sqrt(a))
}
