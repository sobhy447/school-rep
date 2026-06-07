import { useEffect, useState } from 'react'
import api from './api.js'

// العهد: كشف بنود (موظف) ← اعتماد ← تحويل لسند مُرحَّل (محاسب).
const emptyLine = () => ({ petty_cash_item_id: '', amount: '', cost_center_id: '', cost_center_extra_id: '', description: '' })

export default function PettyCash() {
  const [items, setItems] = useState([])
  const [costCenters, setCostCenters] = useState([])
  const [years, setYears] = useState([])
  const [claims, setClaims] = useState([])
  const [head, setHead] = useState({ claim_date: new Date().toISOString().slice(0, 10), description: '' })
  const [lines, setLines] = useState([emptyLine()])
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const reload = async () => {
    const [it, cc, y, cl] = await Promise.all([
      api.get('/petty-cash-items'), api.get('/settings/cost-centers'),
      api.get('/settings/fiscal-years'), api.get('/expense-claims'),
    ])
    setItems(it.data.data); setCostCenters(cc.data.data); setYears(y.data.data); setClaims(cl.data.data)
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [])

  const setLine = (i, patch) => setLines((ls) => ls.map((l, idx) => (idx === i ? { ...l, ...patch } : l)))
  const onItem = (i, itemId) => {
    const it = items.find((x) => String(x.id) === String(itemId))
    setLine(i, { petty_cash_item_id: itemId, amount: it?.default_amount || '', description: it?.name || '' })
  }

  const save = async () => {
    setErr(null); setMsg(null)
    try {
      const payload = { ...head, lines: lines.filter((l) => l.petty_cash_item_id).map((l) => ({
        petty_cash_item_id: Number(l.petty_cash_item_id), amount: Number(l.amount || 0),
        cost_center_id: l.cost_center_id || null, cost_center_extra_id: l.cost_center_extra_id || null,
        description: l.description,
      })) }
      const r = await api.post('/expense-claims', payload)
      setMsg(`تم حفظ الكشف: ${r.data.data.claim_number}`)
      setLines([emptyLine()]); setHead((h) => ({ ...h, description: '' })); reload()
    } catch (e) {
      setErr(e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'تعذّر الحفظ')
    }
  }

  const approve = async (id) => { try { await api.post(`/expense-claims/${id}/approve`); reload() } catch (e) { setErr(e.response?.data?.message) } }
  const convert = async (id) => {
    try { await api.post(`/expense-claims/${id}/convert`, { fiscal_year_id: years[0]?.id }); reload(); setMsg('تم التحويل لسند مُرحَّل') }
    catch (e) { setErr(e.response?.data?.message) }
  }

  return (
    <div style={{ maxWidth: 1000, margin: '20px auto', fontFamily: 'system-ui' }}>
      <h3>العهد (كشف مصروفات)</h3>
      <div style={card}>
        <div style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
          <input type="date" value={head.claim_date} onChange={(e) => setHead({ ...head, claim_date: e.target.value })} />
          <input style={{ flex: 1 }} placeholder="بيان عام" value={head.description} onChange={(e) => setHead({ ...head, description: e.target.value })} />
        </div>
        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
          <thead><tr><th style={th}>البند</th><th style={th}>المبلغ</th><th style={th}>مركز أساسي</th><th style={th}>مركز إضافي</th><th style={th}>بيان</th><th style={th}></th></tr></thead>
          <tbody>
            {lines.map((l, i) => (
              <tr key={i}>
                <td style={td}><select value={l.petty_cash_item_id} onChange={(e) => onItem(i, e.target.value)}>
                  <option value="">— بند —</option>
                  {items.map((it) => <option key={it.id} value={it.id}>{it.code} {it.name}</option>)}
                </select></td>
                <td style={td}><input type="number" step="0.001" value={l.amount} onChange={(e) => setLine(i, { amount: e.target.value })} style={{ width: 80 }} /></td>
                <td style={td}><select value={l.cost_center_id} onChange={(e) => setLine(i, { cost_center_id: e.target.value })}>
                  <option value="">—</option>{costCenters.map((c) => <option key={c.id} value={c.id}>{c.code} {c.name}</option>)}
                </select></td>
                <td style={td}><select value={l.cost_center_extra_id} onChange={(e) => setLine(i, { cost_center_extra_id: e.target.value })}>
                  <option value="">—</option>{costCenters.map((c) => <option key={c.id} value={c.id}>{c.code} {c.name}</option>)}
                </select></td>
                <td style={td}><input value={l.description} onChange={(e) => setLine(i, { description: e.target.value })} style={{ width: 140 }} /></td>
                <td style={td}><button onClick={() => setLines((ls) => ls.length > 1 ? ls.filter((_, x) => x !== i) : ls)} style={{ color: 'crimson', border: 0, background: 'none', cursor: 'pointer' }}>×</button></td>
              </tr>
            ))}
          </tbody>
        </table>
        <div style={{ marginTop: 6, display: 'flex', gap: 8 }}>
          <button onClick={() => setLines((ls) => [...ls, emptyLine()])} style={btn('#2563eb')}>+ بند</button>
          <button onClick={save} style={btn('#16a34a')}>حفظ الكشف</button>
        </div>
      </div>
      {msg && <p style={{ color: '#16a34a' }}>{msg}</p>}
      {err && <p style={{ color: 'crimson' }}>{err}</p>}

      <h4 style={{ marginTop: 24 }}>كشوف العهد</h4>
      <table style={{ width: '100%', borderCollapse: 'collapse', background: '#fff', fontSize: 13 }}>
        <thead><tr><th style={th}>الرقم</th><th style={th}>التاريخ</th><th style={th}>الإجمالي</th><th style={th}>الحالة</th><th style={th}>إجراءات</th></tr></thead>
        <tbody>
          {claims.map((c) => (
            <tr key={c.id}>
              <td style={td}>{c.claim_number}</td><td style={td}>{String(c.claim_date).slice(0, 10)}</td>
              <td style={td}>{Number(c.total_amount).toFixed(3)}</td><td style={td}>{statusAr(c.status)}</td>
              <td style={td}>
                {(c.status === 'DRAFT' || c.status === 'SUBMITTED') && <button onClick={() => approve(c.id)} style={mini}>اعتماد</button>}
                {c.status === 'APPROVED' && <button onClick={() => convert(c.id)} style={mini}>تحويل لسند</button>}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

const statusAr = (s) => ({ DRAFT: 'مسودة', SUBMITTED: 'مُرسل', APPROVED: 'معتمد', CONVERTED: 'محوّل لسند' }[s] || s)
const btn = (bg) => ({ background: bg, color: '#fff', border: 0, borderRadius: 6, padding: '6px 16px', cursor: 'pointer' })
const mini = { background: '#1d4ed8', color: '#fff', border: 0, borderRadius: 4, padding: '3px 8px', cursor: 'pointer', fontSize: 12 }
const card = { background: '#fff', padding: 12, borderRadius: 8 }
const th = { textAlign: 'start', padding: 6, borderBottom: '2px solid #e5e7eb' }
const td = { padding: 4, borderBottom: '1px solid #f0f0f0' }
