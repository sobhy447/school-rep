import { useEffect, useState } from 'react'
import api from './api.js'

export default function Dashboard() {
  const [d, setD] = useState(null)
  const [err, setErr] = useState(null)
  useEffect(() => {
    api.get('/reports/dashboard').then((r) => setD(r.data.data)).catch(() => setErr('تعذّر التحميل'))
  }, [])

  if (err) return <p className="err">{err}</p>
  if (!d) return <p className="muted">جارٍ التحميل…</p>

  const fmt = (v) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
  const cards = [
    ['إجمالي الأصول', d.total_assets, '#2563eb', '🏦'],
    ['إجمالي الخصوم', d.total_liabilities, '#dc2626', '📉'],
    ['حقوق الملكية + النتيجة', d.total_equity_with_result, '#7c3aed', '🏛️'],
    ['صافي الربح/الخسارة', d.net_profit, d.net_profit >= 0 ? '#16a34a' : '#dc2626', '💰'],
    ['الإيرادات', d.total_revenues, '#16a34a', '⬆️'],
    ['المصروفات', d.total_expenses, '#ea580c', '⬇️'],
    ['صافي التدفق النقدي', d.net_cash_flow, '#0891b2', '💵'],
  ]

  return (
    <div>
      <div className="kpi-grid">
        {cards.map(([label, val, color, ico]) => (
          <div className="kpi" key={label} style={{ borderInlineStart: `4px solid ${color}` }}>
            <span className="ico">{ico}</span>
            <div className="label">{label}</div>
            <div className="value" style={{ color }}>{fmt(val)} <small style={{ fontSize: 13, color: 'var(--muted)' }}>د.ك</small></div>
          </div>
        ))}
      </div>
      <div className="card" style={{ marginTop: 16 }}>
        <span className={`badge ${d.balance_sheet_balanced ? 'badge-green' : 'badge-red'}`}>
          {d.balance_sheet_balanced ? '✓ الميزانية متوازنة (أصول = خصوم + حقوق ملكية)' : '✗ الميزانية غير متوازنة'}
        </span>
      </div>
    </div>
  )
}
