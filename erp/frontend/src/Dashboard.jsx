import { useEffect, useState } from 'react'
import api from './api.js'

// لوحة المؤشرات المالية.
export default function Dashboard() {
  const [d, setD] = useState(null)
  const [err, setErr] = useState(null)
  useEffect(() => {
    api.get('/reports/dashboard').then((r) => setD(r.data.data)).catch(() => setErr('تعذّر التحميل'))
  }, [])

  if (err) return <p style={{ color: 'crimson', textAlign: 'center' }}>{err}</p>
  if (!d) return <p style={{ textAlign: 'center' }}>...</p>

  const cards = [
    ['إجمالي الأصول', d.total_assets, '#2563eb'],
    ['إجمالي الخصوم', d.total_liabilities, '#dc2626'],
    ['حقوق الملكية + النتيجة', d.total_equity_with_result, '#7c3aed'],
    ['صافي الربح/الخسارة', d.net_profit, d.net_profit >= 0 ? '#16a34a' : '#dc2626'],
    ['الإيرادات', d.total_revenues, '#16a34a'],
    ['المصروفات', d.total_expenses, '#ea580c'],
    ['صافي التدفق النقدي', d.net_cash_flow, '#0891b2'],
  ]

  return (
    <div style={{ maxWidth: 1000, margin: '20px auto', fontFamily: 'system-ui' }}>
      <h3>لوحة المؤشرات</h3>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 12 }}>
        {cards.map(([label, val, color]) => (
          <div key={label} style={{ background: '#fff', borderRadius: 10, padding: 16, borderInlineStart: `5px solid ${color}` }}>
            <div style={{ color: '#6b7280', fontSize: 13 }}>{label}</div>
            <div style={{ fontSize: 24, fontWeight: 700, color }}>{Number(val).toFixed(3)}</div>
          </div>
        ))}
      </div>
      <p style={{ marginTop: 12, color: d.balance_sheet_balanced ? '#16a34a' : '#dc2626' }}>
        {d.balance_sheet_balanced ? '✓ الميزانية متوازنة (أصول = خصوم + حقوق ملكية)' : '✗ الميزانية غير متوازنة — راجع القيود'}
      </p>
    </div>
  )
}
