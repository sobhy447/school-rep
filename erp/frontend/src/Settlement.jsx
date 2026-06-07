import { useEffect, useState } from 'react'
import api from './api.js'

// نظام الأمانات/السداد: يسار = أمانات (أحمر/أصفر) · يمين = استحقاقات · سداد بالتخصيص.
export default function Settlement() {
  const [customers, setCustomers] = useState([])
  const [accountId, setAccountId] = useState('')
  const [view, setView] = useState(null)
  const [selTrust, setSelTrust] = useState(null)
  const [amounts, setAmounts] = useState({}) // entitlement_line_id -> amount
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const loadCustomers = async () => {
    const r = await api.get('/accounts/parties?type=CUSTOMER')
    setCustomers(r.data.data)
  }
  const loadView = async (id) => {
    setErr(null); setMsg(null); setSelTrust(null); setAmounts({})
    const r = await api.get(`/settlements/customer/${id}`)
    setView(r.data.data)
  }
  useEffect(() => { loadCustomers().catch(() => setErr('تعذّر التحميل')) }, [])
  useEffect(() => { if (accountId) loadView(accountId).catch(() => setErr('تعذّر التحميل')) }, [accountId])

  const allocate = async () => {
    setErr(null); setMsg(null)
    if (!selTrust) { setErr('اختر أمانة من اليسار أولاً'); return }
    const allocations = Object.entries(amounts)
      .filter(([, v]) => Number(v) > 0)
      .map(([entId, v]) => ({ trust_line_id: selTrust, entitlement_line_id: Number(entId), amount: Number(v) }))
    if (allocations.length === 0) { setErr('أدخل مبلغ سداد على استحقاق واحد على الأقل'); return }
    try {
      const r = await api.post(`/settlements/customer/${accountId}/allocate`, { allocations })
      setView(r.data.data); setSelTrust(null); setAmounts({})
      setMsg('تم السداد')
    } catch (e) {
      setErr(e.response?.data?.message || 'تعذّر السداد')
    }
  }

  const colorBg = (c) => (c === 'RED' ? '#fee2e2' : c === 'YELLOW' ? '#fef9c3' : '#fff')

  return (
    <div style={{ maxWidth: 1100, margin: '20px auto', fontFamily: 'system-ui' }}>
      <h3>نظام الأمانات والسداد</h3>
      <select value={accountId} onChange={(e) => setAccountId(e.target.value)} style={{ marginBottom: 12 }}>
        <option value="">— اختر العميل —</option>
        {customers.map((c) => <option key={c.id} value={c.id}>{c.code} — {c.name}</option>)}
      </select>

      {view && (
        <>
          <div style={{ display: 'flex', gap: 12, marginBottom: 8 }}>
            <span style={{ background: '#dbeafe', padding: '4px 10px', borderRadius: 6 }}>إجمالي الأمانات المتبقية: {view.total_trust_remaining}</span>
            <span style={{ background: '#fee2e2', padding: '4px 10px', borderRadius: 6 }}>إجمالي الاستحقاقات المتبقية: {view.total_entitlement_remaining}</span>
          </div>
          <div style={{ display: 'flex', gap: 12 }}>
            {/* يسار: الأمانات */}
            <div style={{ flex: 1 }}>
              <h4>الأمانات (سندات القبض)</h4>
              {view.trusts.length === 0 && <p>لا توجد أمانات</p>}
              {view.trusts.map((t) => (
                <div key={t.line_id} onClick={() => setSelTrust(t.line_id)}
                  style={{ background: colorBg(t.color), border: selTrust === t.line_id ? '2px solid #1d4ed8' : '1px solid #e5e7eb',
                           borderRadius: 8, padding: 10, marginBottom: 8, cursor: 'pointer' }}>
                  <div><b>{t.entry_number}</b> — {t.date}</div>
                  <div>{t.description}</div>
                  <div>المبلغ: {t.original} · المتبقي: <b>{t.remaining}</b></div>
                </div>
              ))}
            </div>
            {/* يمين: الاستحقاقات */}
            <div style={{ flex: 1 }}>
              <h4>الاستحقاقات</h4>
              {view.entitlements.length === 0 && <p>لا توجد استحقاقات</p>}
              {view.entitlements.map((e) => (
                <div key={e.line_id} style={{ background: colorBg(e.color), border: '1px solid #e5e7eb', borderRadius: 8, padding: 10, marginBottom: 8 }}>
                  <div><b>{e.entry_number}</b> — {e.date}</div>
                  <div>{e.description} {e.counterparty_name ? `· خصم: ${e.counterparty_name}` : ''}</div>
                  <div>المبلغ: {e.original} · المتبقي: <b>{e.remaining}</b></div>
                  <div>المسدد: <input type="number" step="0.001" max={e.remaining} style={{ width: 100 }}
                    value={amounts[e.line_id] || ''} onChange={(ev) => setAmounts({ ...amounts, [e.line_id]: ev.target.value })} /></div>
                </div>
              ))}
            </div>
          </div>
          <button onClick={allocate} disabled={!selTrust}
            style={{ marginTop: 10, background: selTrust ? '#16a34a' : '#9ca3af', color: '#fff', border: 0, borderRadius: 6, padding: '8px 20px', cursor: 'pointer' }}>
            حفظ السداد {selTrust ? '(من الأمانة المختارة)' : '(اختر أمانة أولاً)'}
          </button>
          {msg && <p style={{ color: '#16a34a' }}>{msg}</p>}
          {err && <p style={{ color: 'crimson' }}>{err}</p>}
        </>
      )}
    </div>
  )
}
