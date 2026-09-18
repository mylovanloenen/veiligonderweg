export function timeAgo(iso: string): string {
  const diffMin = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000))
  if (diffMin < 1) return 'zojuist'
  if (diffMin < 60) return `${diffMin} min geleden`
  const h = Math.floor(diffMin / 60)
  if (h < 24) return `${h} uur geleden`
  return `${Math.floor(h / 24)} dagen geleden`
}

export function minutesUntil(iso: string): number {
  return Math.max(0, Math.round((new Date(iso).getTime() - Date.now()) / 60000))
}

export function isRecent(iso: string, minutes = 30): boolean {
  return Date.now() - new Date(iso).getTime() < minutes * 60000
}

export const INCIDENT_COLORS: Record<string, string> = {
  beroving: '#dc2626',
  geweld: '#7f1d1d',
  intimidatie: '#ea580c',
  slechte_verlichting: '#ca8a04',
  overig: '#4b5563',
}

export const INCIDENT_ICONS: Record<string, string> = {
  beroving: '💰',
  geweld: '⚠️',
  intimidatie: '🗣️',
  slechte_verlichting: '💡',
  overig: '❔',
}

/** Rustige, sequentiele kleuren voor de statistische laag (bewust ingetogen). */
export const CLASS_COLORS: Record<number, string> = {
  1: '#dbeafe',
  2: '#93c5fd',
  3: '#60a5fa',
  4: '#2563eb',
  5: '#1e3a8a',
}

export const CLASS_LABELS: Record<number, string> = {
  1: 'Laag',
  2: 'Onder gemiddeld',
  3: 'Gemiddeld',
  4: 'Boven gemiddeld',
  5: 'Hoog',
}
