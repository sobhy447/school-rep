import { useEffect, useState } from 'react'
import api from './api.js'

const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })

export default function Settlement() {
  const [customers, setCustomers] = useState([])
  const [accountId, setAccountId] = useState('')
  const [view, setView] = useState(null)
  const [selTrust, setSelTrust] = useState(null)
  const [amounts, setAmounts] = useState({})
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  useEffect(() => { api.get('/accounts/parties?type=CUSTOMER').then((r) => setCustomers(r.data.data)).catch(() => setErr('تعذّر التحميل')) }, [])
  useEffect(() => {
    if (!accountId) return
    setMsg(null); setErr(null); setSelTrust(null); setAmounts({})
    api.get(`/settlements/customer/${accountId}`).then((r) => setView(r.data.data)).catch(() => setErr('تعذّر التحميل'))
  }, [accountId])

  const allocate = async () => {
    setErr(null); setMsg(null)
    if (!selTrust) { setErr('اختر أمانة من اليمين أولاً'); return }
    const allocations = Object.entries(amounts).filter(([, v]) => Number(v) > 0)
      .map(([entId, v]) => ({ trust_line_id: selTrust, entitlement_line_id: Number(entId), amount: Number(v) }))
    if (!allocations.length) { setErr('أدخل مبلغ سداد على استحقاق واحد على الأقل'); return }
    try {
      const r = await api.post(`/settlements/customer/${accountId}/allocate`, { allocations })
      setView(r.data.data); setSelTrust(null); setAmounts({}); setMsg('تم السداد بنجاح')
    } catch (e) { setErr(e.response?.data?.message || 'تعذّر السداد') }
  }

  const badge = (c) => c === 'RED' ? 'badge-red' : c === 'YELLOW' ? 'badge-amber' : 'badge-gray'
  const cardColor = (c) => c === 'RED' ? '#fef2f2' : c === 'YELLOW' ? '#fffbeb' : '#fff'

  return (
    <div>
      <div className="card">
        <div className="row">
          <label>العميل:</label>
          <select value={accountId} onChange={(e) => setAccountId(e.target.value)} style={{ minWidth: 260 }}>
            <option value="">— اختر العميل —</option>
            {customers.map((c) => <option key={c.id} value={c.id}>{c.code} — {c.name}</option>)}
          </select>
        </div>
      </div>

      {view && (
        <>
          <div className="row" style={{ marginBottom: 14 }}>
            <span className="badge badge-blue">إجمالي الأمانات المتبقية: {n(view.total_trust_remaining)}</span>
            <span className="badge badge-red">إجمالي الاستحقاقات المتبقية: {n(view.total_entitlement_remaining)}</span>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
            <div className="card">
              <h4>🟥 الأمانات (سندات القبض)</h4>
              <p className="muted" style={{ fontSize: 12 }}>اختر أمانة لتسدّد منها</p>
              {view.trusts.length === 0 && <p className="muted">لا توجد أمانات</p>}
              {view.trusts.map((t) => (
                <div key={t.line_id} onClick={() => setSelTrust(t.line_id)}
                  style={{ background: cardColor(t.color), border: selTrust === t.line_id ? '2px solid var(--primary-600)' : '1px solid var(--border)', borderRadius: 10, padding: 12, marginBottom: 10, cursor: 'pointer' }}>
                  <div className="row" style={{ justifyContent: 'space-between' }}>
                    <b>{t.entry_number}</b><span className="muted">{t.date}</span>
                  </div>
                  <div className="muted" style={{ fontSize: 13 }}>{t.description}</div>
                  <div className="row" style={{ justifyContent: 'space-between', marginTop: 6 }}>
                    <span>المبلغ: {n(t.original)}</span>
                    <span className={`badge ${badge(t.color)}`}>المتبقي: {n(t.remaining)}</span>
                  </div>
                </div>
              ))}
            </div>
            <div className="card">
              <h4>🧾 الاستحقاقات</h4>
              <p className="muted" style={{ fontSize: 12 }}>أدخل المبلغ المسدّد لكل بند</p>
              {view.entitlements.length === 0 && <p className="muted">لا توجد استحقاقات</p>}
              {view.entitlements.map((e) => (
                <div key={e.line_id} style={{ background: cardColor(e.color), border: '1px solid var(--border)', borderRadius: 10, padding: 12, marginBottom: 10 }}>
                  <div className="row" style={{ justifyContent: 'space-between' }}>
                    <b>{e.entry_number}</b><span className="muted">{e.date}</span>
                  </div>
                  <div className="muted" style={{ fontSize: 13 }}>{e.description} {e.counterparty_name ? `· خصم: ${e.counterparty_name}` : ''}</div>
                  <div className="row" style={{ justifyContent: 'space-between', marginTop: 6 }}>
                    <span>المبلغ: {n(e.original)} · المتبقي: <b>{n(e.remaining)}</b></span>
                  </div>
                  <div className="row" style={{ marginTop: 6 }}>
                    <label>المسدّد:</label>
                    <input type="number" step="0.001" max={e.remaining} style={{ width: 120 }}
                      value={amounts[e.line_id] || ''} onChange={(ev) => setAmounts({ ...amounts, [e.line_id]: ev.target.value })} />
                  </div>
                </div>
              ))}
            </div>
          </div>
          <button className="btn-success" style={{ marginTop: 14 }} onClick={allocate} disabled={!selTrust}>
            💾 حفظ السداد {selTrust ? '' : '(اختر أمانة أولاً)'}
          </button>
          {msg && <p className="ok">{msg}</p>}
          {err && <p className="err">{err}</p>}
        </>
      )}
    </div>
  )
}
