import { useState, type FormEvent } from 'react'
import { ApiError } from '../api/client'

interface Props {
  onLogin: (email: string, password: string) => Promise<void>
  onRegister: (name: string, email: string, password: string) => Promise<void>
  onClose: () => void
}

export function AuthModal({ onLogin, onRegister, onClose }: Props) {
  const [mode, setMode] = useState<'login' | 'register'>('login')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function submit(e: FormEvent) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      if (mode === 'login') await onLogin(email, password)
      else await onRegister(name, email, password)
      onClose()
    } catch (err) {
      if (err instanceof ApiError) {
        const first = Object.values(err.errors)[0]?.[0]
        setError(first ?? err.message)
      } else setError('Er ging iets mis.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <form className="modal" onClick={(e) => e.stopPropagation()} onSubmit={submit}>
        <div className="modal__tabs">
          <button type="button" className={mode === 'login' ? 'active' : ''} onClick={() => setMode('login')}>Inloggen</button>
          <button type="button" className={mode === 'register' ? 'active' : ''} onClick={() => setMode('register')}>Account maken</button>
        </div>
        <p className="muted">Een account is alleen nodig om te melden of te beoordelen. We slaan geen locatiegeschiedenis op.</p>
        {mode === 'register' && (
          <label>
            Naam
            <input value={name} onChange={(e) => setName(e.target.value)} required maxLength={60} autoComplete="name" />
          </label>
        )}
        <label>
          E-mail
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" />
        </label>
        <label>
          Wachtwoord
          <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} autoComplete={mode === 'login' ? 'current-password' : 'new-password'} />
        </label>
        {error && <div className="error">{error}</div>}
        <div className="modal__actions">
          <button type="button" className="btn btn--ghost" onClick={onClose}>Annuleren</button>
          <button type="submit" className="btn btn--primary" disabled={busy}>
            {busy ? 'Bezig…' : mode === 'login' ? 'Inloggen' : 'Account maken'}
          </button>
        </div>
      </form>
    </div>
  )
}
