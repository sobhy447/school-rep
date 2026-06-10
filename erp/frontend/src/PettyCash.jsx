import { useEffect, useState } from 'react'
import api from './api.js'

const emptyLine = () => ({ petty_cash_item_id: '', amount: '', cost_center_id: '', cost_center_extra_id: '', description: '' })
const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const statusAr = (s) => ({ DRAFT: 'مسودة', SUBMITTED: 'مُرسل', APPROVED: 'معتمد', CONVERTED: 'محوّل لسند' }[s] || s)
const statusBadge = (s) => ({ DRAFT: 'badge-gray', SUBMITTED: 'badge-blue', APPROVED: 'badge-amber', CONVERTED: 'badge-green' }[s] || 'badge-gray')

export default function PettyCash() {
  const [items, setItems] = useState([]); const [costCenters, setCostCenters] = useState([]); const [years, setYears] = useState([]); const [claims, setClaims] = useState([])
  const [head, setHead] = useState({ claim_date: new Date().toISOString().slice(0, 10), description: '' })
  const [lines, setLines] = useState([emptyLine()]); const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const reload = async () => {
    const [it, cc, y, cl] = await Promise.all([api.get('/petty-cash-items'), api.get('/settings/cost-centers'), api.get('/settings/fiscal-years'), api.get('/expense-claims')])
    setItems(it.data.data); setCostCenters(cc.data.data); setYears(y.data.data); setClaims(cl.data.data)
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [])

  const setLine = (i, patch) => setLines((ls) => ls.map((l, idx) => (idx === i ? { ...l, ...patch } : l)))
  const onItem = (i, itemId) => { const it = items.find((x) => String(x.id) === String(itemId)); setLine(i, { petty_cash_item_id: itemId, amount: it?.default_amount || '', description: it?.name || '' }) }

  const save = async () => {
    setErr(null); setMsg(null)
    try {
      const payload = { ...head, lines: lines.filter((l) => l.petty_cash_item_id).map((l) => ({
        petty_cash_item_id: Number(l.petty_cash_item_id), amount: Number(l.amount || 0),
        cost_center_id: l.cost_center_id || null, cost_center_extra_id: l.cost_center_extra_id || null, description: l.description })) }
      const r = await api.post('/expense-claims', payload)
      setMsg(`تم حفظ الكشف: ${r.data.data.claim_number}`); setLines([emptyLine()]); setHead((h) => ({ ...h, description: '' })); reload()
    } catch (e) { setErr(e.response?.data?.message || 'تعذّر الحفظ') }
  }
  const approve = async (id) => { try { await api.post(`/expense-claims/${id}/approve`); reload() } catch (e) { setErr(e.response?.data?.message) } }
  const convert = async (id) => { try { await api.post(`/expense-claims/${id}/convert`, { fiscal_year_id: years[0]?.id }); reload(); setMsg('تم التحويل لسند مُرحَّل') } catch (e) { setErr(e.response?.data?.message) } }

  return (
    <div>
      <div className="card">
        <h4>كشف عهدة جديد</h4>
        <div className="row" style={{ marginBottom: 12 }}>
          <input type="date" value={head.claim_date} onChange={(e) => setHead({ ...head, claim_date: e.target.value })} />
          <input style={{ flex: 1 }} placeholder="بيان عام" value={head.description} onChange={(e) => setHead({ ...head, description: e.target.value })} />
        </div>
        <table>
          <thead><tr><th>البند</th><th>المبلغ</th><th>مركز أساسي</th><th>مركز إضافي</th><th>بيان</th><th></th></tr></thead>
          <tbody>
            {lines.map((l, i) => (
              <tr key={i}>
                <td><select value={l.petty_cash_item_id} onChange={(e) => onItem(i, e.target.value)}><option value="">— بند —</option>{items.map((it) => <option key={it.id} value={it.id}>{it.code} {it.name}</option>)}</select></td>
                <td><input type="number" step="0.001" value={l.amount} onChange={(e) => setLine(i, { amount: e.target.value })} style={{ width: 90 }} /></td>
                <td><select value={l.cost_center_id} onChange={(e) => setLine(i, { cost_center_id: e.target.value })}><option value="">—</option>{costCenters.map((c) => <option key={c.id} value={c.id}>{c.code} {c.name}</option>)}</select></td>
                <td><select value={l.cost_center_extra_id} onChange={(e) => setLine(i, { cost_center_extra_id: e.target.value })}><option value="">—</option>{costCenters.map((c) => <option key={c.id} value={c.id}>{c.code} {c.name}</option>)}</select></td>
                <td><input value={l.description} onChange={(e) => setLine(i, { description: e.target.value })} style={{ width: 140 }} /></td>
                <td><button className="btn-danger btn-sm" onClick={() => setLines((ls) => ls.length > 1 ? ls.filter((_, x) => x !== i) : ls)}>×</button></td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="toolbar" style={{ marginTop: 10 }}>
          <button className="btn" onClick={() => setLines((ls) => [...ls, emptyLine()])}>+ بند</button>
          <button className="btn-success" onClick={save}>💾 حفظ الكشف</button>
        </div>
        {msg && <p className="ok">{msg}</p>}
        {err && <p className="err">{err}</p>}
      </div>

      <div className="card">
        <h4>كشوف العهد</h4>
        <table>
          <thead><tr><th>الرقم</th><th>التاريخ</th><th>الإجمالي</th><th>الحالة</th><th>إجراءات</th></tr></thead>
          <tbody>
            {claims.map((c) => (
              <tr key={c.id}>
                <td>{c.claim_number}</td><td>{String(c.claim_date).slice(0, 10)}</td><td>{n(c.total_amount)}</td>
                <td><span className={`badge ${statusBadge(c.status)}`}>{statusAr(c.status)}</span></td>
                <td>
                  {(c.status === 'DRAFT' || c.status === 'SUBMITTED') && <button className="btn-primary btn-sm" onClick={() => approve(c.id)}>اعتماد</button>}
                  {c.status === 'APPROVED' && <button className="btn-primary btn-sm" onClick={() => convert(c.id)}>تحويل لسند</button>}
                </td>
              </tr>
            ))}
            {claims.length === 0 && <tr><td colSpan={5} className="muted">لا توجد كشوف</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  )
}
