/**
 * Dunne client voor de VeiligOnderweg REST-API (/api/v1).
 * Bewust framework-loos zodat de Expo-app dezelfde functies kan hergebruiken.
 */
export const API_URL = (import.meta.env.VITE_API_URL as string | undefined) ?? 'http://localhost:8000'

export type CrimeCategoryKey = 'total' | 'violence' | 'robbery' | 'burglary' | 'theft' | 'nuisance'

export interface CrimeCategory {
  key: CrimeCategoryKey
  label: string
  codes: string[]
}

export interface NeighbourhoodProps {
  code: string
  name: string
  district_code: string | null
  population: number | null
  is_water: boolean
  category: CrimeCategoryKey
  year: number
  count: number | null
  rate_per_1000: number | null
  score: number | null
  class: 1 | 2 | 3 | 4 | 5 | null
}

export interface NeighbourhoodCollection {
  type: 'FeatureCollection'
  meta: { category: CrimeCategoryKey; year: number; count: number }
  features: Array<{ type: 'Feature'; id: string; geometry: GeoJSON.Geometry; properties: NeighbourhoodProps }>
}

export interface IncidentCategory {
  slug: string
  name: string
  ttl_minutes: number
}

export interface Incident {
  id: number
  category: { slug: string; name: string }
  lat: number
  lng: number
  description: string | null
  confirmations: number
  disputes: number
  created_at: string
  expires_at: string
  hidden: boolean
  distance_m: number | null
}

export interface User {
  id: number
  name: string
  email: string
}

export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>

  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }
}

const TOKEN_KEY = 'vo_token'

export function getToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export function setToken(token: string | null): void {
  try {
    if (token) localStorage.setItem(TOKEN_KEY, token)
    else localStorage.removeItem(TOKEN_KEY)
  } catch {
    /* privémodus: geen persistente login */
  }
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...(init.body ? { 'Content-Type': 'application/json' } : {}),
    ...((init.headers as Record<string, string>) ?? {}),
  }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const response = await fetch(`${API_URL}/api/v1${path}`, { ...init, headers })
  const text = await response.text()
  const json = text ? JSON.parse(text) : {}

  if (!response.ok) {
    throw new ApiError(response.status, json.message ?? `Fout ${response.status}`, json.errors ?? {})
  }
  return json as T
}

export const api = {
  crimeCategories: () => request<{ data: CrimeCategory[] }>('/neighbourhoods/categories').then((r) => r.data),

  neighbourhoods: (category: CrimeCategoryKey, municipality = 'GM0363') =>
    request<NeighbourhoodCollection>(`/neighbourhoods?category=${category}&municipality=${municipality}`),

  incidentCategories: () => request<{ data: IncidentCategory[] }>('/categories').then((r) => r.data),

  incidentsInBbox: (minLng: number, minLat: number, maxLng: number, maxLat: number) =>
    request<{ data: Incident[] }>(`/incidents?bbox=${minLng},${minLat},${maxLng},${maxLat}`).then((r) => r.data),

  incidentsNear: (lat: number, lng: number, radius = 1000) =>
    request<{ data: Incident[] }>(`/incidents?lat=${lat}&lng=${lng}&radius=${radius}`).then((r) => r.data),

  createIncident: (body: { category: string; lat: number; lng: number; description?: string }) =>
    request<{ data: Incident }>('/incidents', { method: 'POST', body: JSON.stringify(body) }).then((r) => r.data),

  confirm: (id: number) => request<{ data: Incident }>(`/incidents/${id}/confirm`, { method: 'POST' }).then((r) => r.data),
  dispute: (id: number) => request<{ data: Incident }>(`/incidents/${id}/dispute`, { method: 'POST' }).then((r) => r.data),

  register: (body: { name: string; email: string; password: string }) =>
    request<{ token: string; user: User }>('/auth/register', { method: 'POST', body: JSON.stringify(body) }),
  login: (body: { email: string; password: string }) =>
    request<{ token: string; user: User }>('/auth/login', { method: 'POST', body: JSON.stringify(body) }),
  logout: () => request<{ message: string }>('/auth/logout', { method: 'POST' }),
  me: () => request<{ data: User }>('/auth/me').then((r) => r.data),
}
