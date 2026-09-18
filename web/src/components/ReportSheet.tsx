import { useState, type FormEvent } from 'react'
import { ApiError, type IncidentCategory } from '../api/client'
import { INCIDENT_COLORS, INCIDENT_ICONS } from '../format'

interface Props {
  categories: IncidentCategory[]
  location: { lat: number; lng: number } | null
  onUseGps: () => void
  gpsBusy: boolean
  onSubmit: (body: { category: string; lat: number; lng: number; description?: string }) => Promise<void>
  onCancel: () => void
}

const MAX = 200

export function ReportSheet({ categories, location, onUseGps, gpsBusy, onSubmit, onCancel }: Props) {
  const [category, setCategory] = useState<string>(categories[0]?.slug ?? 'overig')
  const [description, setDescription] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function submit(e: FormEvent) {
    e.preventDefault()
    if (!location) return
    setBusy(true)
    setError(null)
    try {
      await onSubmit({ category, lat: location.lat, lng: location.lng, description: description.trim() || undefined })
    } catch (err) {
      if (err instanceof ApiError) setError(Object.values(err.errors)[0]?.[0] ?? err.message)
      else setError('Melden mislukt.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <form className="sheet" onSubmit={submit}>
      <div className="sheet__header">
        <strong>Onveilige situatie melden</strong>
        <button type="button" className="btn btn--ghost" onClick={onCancel} aria-label="Sluiten">✕</button>
      </div>

      <div className="sheet__section">
        <div className="sheet__label">1. Waar?</div>
        <div className="sheet__row">
          <button type="button" className="btn" onClick={onUseGps} disabled={gpsBusy}>
            {gpsBusy ? 'Locatie ophalen…' : '📍 Mijn locatie'}
          </button>
          <span className="muted">of tik op de kaart</span>
        </div>
        <div className={`sheet__location ${location ? 'ok' : ''}`}>
          {location ? `Gekozen: ${location.lat.toFixed(5)}, ${location.lng.toFixed(5)}` : 'Nog geen locatie gekozen'}
        </div>
      </div>

      <div className="sheet__section">
        <div className="sheet__label">2. Wat?</div>
        <div className="chips">
          {categories.map((c) => (
            <button
              key={c.slug}
              type="button"
              className={`chip ${category === c.slug ? 'chip--active' : ''}`}
              style={category === c.slug ? { background: INCIDENT_COLORS[c.slug], borderColor: INCIDENT_COLORS[c.slug] } : undefined}
              onClick={() => setCategory(c.slug)}
            >
              {INCIDENT_ICONS[c.slug]} {c.name}
            </button>
          ))}
        </div>
      </div>

      <div className="sheet__section">
        <div className="sheet__label">3. Korte omschrijving (optioneel)</div>
        <textarea
          value={description}
          maxLength={MAX}
          rows={3}
          placeholder="Beschrijf de situatie, niet de persoon. Geen namen, kentekens of uiterlijk."
          onChange={(e) => setDescription(e.target.value)}
        />
        <div className="muted right">{description.length}/{MAX}</div>
      </div>

      {error && <div className="error">{error}</div>}

      <div className="sheet__actions">
        <button type="button" className="btn btn--ghost" onClick={onCancel}>Annuleren</button>
        <button type="submit" className="btn btn--primary" disabled={!location || busy}>
          {busy ? 'Versturen…' : 'Melding plaatsen'}
        </button>
      </div>
      <div className="muted small">Meldingen verlopen automatisch en worden na verloop losgekoppeld van je account.</div>
    </form>
  )
}
