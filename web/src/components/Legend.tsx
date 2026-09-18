import { CLASS_COLORS, CLASS_LABELS } from '../format'

interface Props {
  categoryLabel: string
  year: number | null
  visible: boolean
}

export function Legend({ categoryLabel, year, visible }: Props) {
  if (!visible) return null
  return (
    <div className="legend" role="group" aria-label="Legenda statistische laag">
      <div className="legend__title">
        {categoryLabel}
        {year ? <span className="legend__year"> · politiecijfers {year}</span> : null}
      </div>
      <div className="legend__scale">
        {[1, 2, 3, 4, 5].map((c) => (
          <div key={c} className="legend__item">
            <span className="legend__swatch" style={{ background: CLASS_COLORS[c] }} />
            <span>{CLASS_LABELS[c]}</span>
          </div>
        ))}
        <div className="legend__item">
          <span className="legend__swatch legend__swatch--na" />
          <span>Onvoldoende data</span>
        </div>
      </div>
      <div className="legend__note">Per 1.000 inwoners, rang binnen de gemeente. Zegt niets over individuele bewoners.</div>
    </div>
  )
}
