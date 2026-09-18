import { useCallback, useEffect, useRef, useState } from 'react'
import type L from 'leaflet'
import { api, ApiError, IS_DEMO, type CrimeCategory, type CrimeCategoryKey, type Incident, type IncidentCategory, type NeighbourhoodCollection } from './api/client'
import { useAuth } from './hooks/useAuth'
import { EmergencyBanner } from './components/EmergencyBanner'
import { MapView } from './components/MapView'
import { Legend } from './components/Legend'
import { ReportSheet } from './components/ReportSheet'
import { AuthModal } from './components/AuthModal'
import { INCIDENT_COLORS, INCIDENT_ICONS, timeAgo } from './format'

type Mode = 'browse' | 'report'

export default function App() {
  const auth = useAuth()
  const [crimeCategories, setCrimeCategories] = useState<CrimeCategory[]>([])
  const [category, setCategory] = useState<CrimeCategoryKey>('total')
  const [showStats, setShowStats] = useState(true)
  const [neighbourhoods, setNeighbourhoods] = useState<NeighbourhoodCollection | null>(null)
  const [statsError, setStatsError] = useState<string | null>(null)

  const [incidentCategories, setIncidentCategories] = useState<IncidentCategory[]>([])
  const [incidents, setIncidents] = useState<Incident[]>([])
  const boundsRef = useRef<L.LatLngBounds | null>(null)

  const [mode, setMode] = useState<Mode>('browse')
  const [picked, setPicked] = useState<{ lat: number; lng: number } | null>(null)
  const [gpsBusy, setGpsBusy] = useState(false)
  const [flyTo, setFlyTo] = useState<{ lat: number; lng: number; key: number } | null>(null)
  const [showAuth, setShowAuth] = useState(false)
  const [toast, setToast] = useState<string | null>(null)
  const [votedIds, setVotedIds] = useState<Set<number>>(new Set())
  const [panelOpen, setPanelOpen] = useState(true)

  // Statistische laag
  useEffect(() => {
    api.crimeCategories().then(setCrimeCategories).catch(() => setStatsError('API niet bereikbaar'))
    api.incidentCategories().then(setIncidentCategories).catch(() => {})
  }, [])

  useEffect(() => {
    setStatsError(null)
    api
      .neighbourhoods(category)
      .then(setNeighbourhoods)
      .catch(() => setStatsError('Statistische laag kon niet geladen worden. Is de import gedraaid?'))
  }, [category])

  // Live meldingen: per kaartvenster, ververst elke 60 s
  const loadIncidents = useCallback(async () => {
    const b = boundsRef.current
    if (!b) return
    try {
      setIncidents(await api.incidentsInBbox(b.getWest(), b.getSouth(), b.getEast(), b.getNorth()))
    } catch {
      /* stil falen; volgende poll probeert opnieuw */
    }
  }, [])

  const onBoundsChange = useCallback(
    (b: L.LatLngBounds) => {
      boundsRef.current = b
      void loadIncidents()
    },
    [loadIncidents],
  )

  useEffect(() => {
    const t = setInterval(loadIncidents, 60_000)
    return () => clearInterval(t)
  }, [loadIncidents])

  function showToast(text: string) {
    setToast(text)
    setTimeout(() => setToast(null), 4000)
  }

  function startReport() {
    if (!auth.user) {
      setShowAuth(true)
      return
    }
    setPicked(null)
    setMode('report')
  }

  function useGps() {
    if (!navigator.geolocation) {
      showToast('Locatie niet beschikbaar in deze browser.')
      return
    }
    setGpsBusy(true)
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const loc = { lat: pos.coords.latitude, lng: pos.coords.longitude }
        setPicked(loc)
        setFlyTo({ ...loc, key: Date.now() })
        setGpsBusy(false)
      },
      () => {
        showToast('Locatie ophalen mislukt. Tik op de kaart.')
        setGpsBusy(false)
      },
      { enableHighAccuracy: true, timeout: 10_000 },
    )
  }

  async function submitReport(body: { category: string; lat: number; lng: number; description?: string }) {
    const created = await api.createIncident(body)
    setIncidents((list) => [created, ...list])
    setMode('browse')
    setPicked(null)
    showToast('Melding geplaatst. Bedankt!')
  }

  async function vote(id: number, type: 'confirm' | 'dispute') {
    try {
      const updated = type === 'confirm' ? await api.confirm(id) : await api.dispute(id)
      setVotedIds((s) => new Set(s).add(id))
      setIncidents((list) => (updated.hidden ? list.filter((i) => i.id !== id) : list.map((i) => (i.id === id ? updated : i))))
    } catch (err) {
      showToast(err instanceof ApiError ? err.message : 'Beoordelen mislukt.')
      if (err instanceof ApiError && (err.status === 409 || err.status === 422)) setVotedIds((s) => new Set(s).add(id))
    }
  }

  const categoryLabel = crimeCategories.find((c) => c.key === category)?.label ?? 'Totaal misdrijven'
  const recent = [...incidents].sort((a, b) => b.created_at.localeCompare(a.created_at)).slice(0, 6)

  return (
    <div className="app">
      <EmergencyBanner />
      {IS_DEMO && (
        <div className="demo-banner">
          Demo op GitHub Pages: statische snapshot van Amsterdam. Meldingen en stemmen worden niet opgeslagen.
        </div>
      )}

      <MapView
        neighbourhoods={neighbourhoods}
        showStats={showStats}
        incidents={incidents}
        onBoundsChange={onBoundsChange}
        pickingLocation={mode === 'report'}
        pickedLocation={picked}
        onPickLocation={(lat, lng) => setPicked({ lat, lng })}
        flyTo={flyTo}
        onConfirm={(id) => vote(id, 'confirm')}
        onDispute={(id) => vote(id, 'dispute')}
        canVote={!!auth.user}
        votedIds={votedIds}
      />

      <aside className={`panel ${panelOpen ? '' : 'panel--collapsed'}`}>
        <div className="panel__header">
          <div className="brand">
            <span className="brand__logo" aria-hidden="true">🛡️</span>
            <div>
              <div className="brand__name">VeiligOnderweg</div>
              <div className="brand__sub">Amsterdam · {IS_DEMO ? 'demo' : 'MVP'}</div>
            </div>
          </div>
          <div className="panel__auth">
            {auth.user ? (
              <>
                <span className="muted small">{auth.user.name}</span>
                <button className="btn btn--ghost btn--small" onClick={() => void auth.logout()}>Uitloggen</button>
              </>
            ) : (
              <button className="btn btn--ghost btn--small" onClick={() => setShowAuth(true)}>Inloggen</button>
            )}
            <button className="btn btn--ghost btn--small panel__toggle" onClick={() => setPanelOpen((o) => !o)} aria-label="Paneel in- of uitklappen">
              {panelOpen ? '▾' : '▴'}
            </button>
          </div>
        </div>

        <div className="panel__body">
          <section>
            <div className="section__title">
              <span>Recente meldingen</span>
              <span className="badge">{incidents.length} in beeld</span>
            </div>
            {recent.length === 0 ? (
              <div className="muted small">Geen actieve meldingen in dit kaartvenster.</div>
            ) : (
              <ul className="inc-list">
                {recent.map((i) => (
                  <li key={i.id} onClick={() => setFlyTo({ lat: i.lat, lng: i.lng, key: Date.now() })}>
                    <span className="inc-list__dot" style={{ background: INCIDENT_COLORS[i.category.slug] }}>{INCIDENT_ICONS[i.category.slug]}</span>
                    <div>
                      <div className="inc-list__title">{i.category.name}</div>
                      <div className="muted small">{timeAgo(i.created_at)} · 👍 {i.confirmations}</div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section>
            <div className="section__title">
              <span>Statistische laag</span>
              <label className="switch">
                <input type="checkbox" checked={showStats} onChange={(e) => setShowStats(e.target.checked)} />
                <span>{showStats ? 'aan' : 'uit'}</span>
              </label>
            </div>
            <select value={category} onChange={(e) => setCategory(e.target.value as CrimeCategoryKey)} disabled={!showStats}>
              {crimeCategories.map((c) => (
                <option key={c.key} value={c.key}>{c.label}</option>
              ))}
            </select>
            {statsError && <div className="error small">{statsError}</div>}
            <div className="muted small">Geregistreerde misdrijven per buurt (politie/CBS), per 1.000 inwoners. Cijfers zeggen iets over plekken, niet over mensen.</div>
          </section>
        </div>
      </aside>

      <Legend categoryLabel={categoryLabel} year={neighbourhoods?.meta.year ?? null} visible={showStats && mode === 'browse'} />

      {mode === 'browse' && (
        <button className="fab" onClick={startReport}>
          <span aria-hidden="true">＋</span> Melden
        </button>
      )}

      {mode === 'report' && (
        <ReportSheet
          categories={incidentCategories}
          location={picked}
          onUseGps={useGps}
          gpsBusy={gpsBusy}
          onSubmit={submitReport}
          onCancel={() => {
            setMode('browse')
            setPicked(null)
          }}
        />
      )}

      {showAuth && <AuthModal onLogin={auth.login} onRegister={auth.register} onClose={() => setShowAuth(false)} />}

      {toast && <div className="toast">{toast}</div>}
    </div>
  )
}
