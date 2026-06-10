import { useEffect, useState } from 'react'
import { onToast } from './toast.js'

const ICON = { success: '✓', error: '✕', info: 'ℹ' }

export default function Toaster() {
  const [items, setItems] = useState([])
  useEffect(() => onToast((t) => {
    setItems((s) => [...s, t])
    setTimeout(() => setItems((s) => s.filter((x) => x.id !== t.id)), 3800)
  }), [])

  return (
    <div className="toaster" role="status" aria-live="polite">
      {items.map((t) => (
        <div key={t.id} className={`toast toast-${t.type}`} onClick={() => setItems((s) => s.filter((x) => x.id !== t.id))}>
          <span className={`toast-ico toast-ico-${t.type}`}>{ICON[t.type]}</span>
          <span>{t.message}</span>
        </div>
      ))}
    </div>
  )
}
