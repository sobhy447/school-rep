import { useEffect, useState } from 'react'
import api from './api.js'

const MONTHS = ['ينا', 'فبر', 'مار', 'أبر', 'ماي', 'يون', 'يول', 'أغس', 'سبت', 'أكت', 'نوف', 'ديس']

function TrendChart({ months }) {
  const max = Math.max(1, ...months.map((m) => Math.max(m.revenue, m.expense)))
  const W = 720, H = 220, pad = 30, bw = (W - pad * 2) / 12
  return (
    <div className="card">
      <h4>الإيرادات والمصروفات شهرياً</h4>
      <div style={{ display: 'flex', gap: 16, marginBottom: 6, fontSize: 13 }}>
        <span><span style={{ display: 'inline-block', width: 12, height: 12, background: '#16a34a', borderRadius: 3 }}></span> إيرادات</span>
        <span><span style={{ display: 'inline-block', width: 12, height: 12, background: '#ea580c', borderRadius: 3 }}></span> مصروفات</span>
      </div>
      <svg viewBox={`0 0 ${W} ${H}`} style={{ width: '100%', height: 220 }} dir="ltr">
        {months.map((m, i) => {
          const x = pad + i * bw
          const rh = (m.revenue / max) * (H - pad * 2)
          const eh = (m.expense / max) * (H - pad * 2)
          return (
            <g key={i}>
              <rect x={x + 4} y={H - pad - rh} width={bw / 2 - 5} height={rh} fill="#16a34a" rx="2" />
              <rect x={x + bw / 2} y={H - pad - eh} width={bw / 2 - 5} height={eh} fill="#ea580c" rx="2" />
              <text x={x + bw / 2} y={H - pad + 14} fontSize="10" textAnchor="middle" fill="#94a3b8">{MONTHS[i]}</text>
            </g>
          )
        })}
        <line x1={pad} y1={H - pad} x2={W - pad} y2={H - pad} stroke="#cbd5e1" />
      </svg>
    </div>
  )
}

export default function Dashboard() {
  const [d, setD] = useState(null)
  const [trend, setTrend] = useState(null)
  const [err, setErr] = useState(null)
  useEffect(() => {
    api.get('/reports/dashboard').then((r) => setD(r.data.data)).catch(() => setErr('تعذّر التحميل'))
    api.get('/reports/monthly-trend').then((r) => setTrend(r.data.data)).catch(() => {})
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
      {trend && <div style={{ marginTop: 16 }}><TrendChart months={trend.months} /></div>}
      <div className="card" style={{ marginTop: 16 }}>
        <span className={`badge ${d.balance_sheet_balanced ? 'badge-green' : 'badge-red'}`}>
          {d.balance_sheet_balanced ? '✓ الميزانية متوازنة (أصول = خصوم + حقوق ملكية)' : '✗ الميزانية غير متوازنة'}
        </span>
      </div>
    </div>
  )
}
