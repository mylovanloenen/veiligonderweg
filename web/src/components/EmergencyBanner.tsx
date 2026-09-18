export function EmergencyBanner() {
  return (
    <a className="emergency" href="tel:112" aria-label="Direct gevaar? Bel 112">
      <span className="emergency__dot" aria-hidden="true" />
      <strong>Direct gevaar?</strong>&nbsp;Bel 112
    </a>
  )
}
