import { useEffect, useState } from 'react'
import api from './api.js'

const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const today = () => new Date().toISOString().slice(0, 10)
const emptyLine = () => ({ item_id: '', quantity: '', unit_price: '', tax_rate: '' })

export default function Returns() {
  const [type, setType] = useState('SALES')
  const [parties, setParties] = useState([]); const [items, setItems] = useState([]); const [warehouses, setWarehouses] = useState([]); const [years, setYears] = useState([]); const [list, setList] = useState([])
  const [head, setHead] = useState({ return_date: today() })
  const [lines, setLines] = useState([emptyLine()])
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const partyType = type === 'SALES' ? 'CUSTOMER' : 'VENDOR'
  const reload = async () => {
    const [p, it, wh, y, l] = await Promise.all([
      api.get(`/accounts/parties?type=${partyType}`), api.get('/items'), api.get('/warehouses'), api.get('/settings/fiscal-years'), api.get(`/returns?type=${type}`)])
    setParties(p.data.data); setItems(it.data.data); setWarehouses(wh.data.data); setYears(y.data.data); setList(l.data.data)
    setHead((h) => ({ ...h, fiscal_year_id: h.fiscal_year_id || y.data.data[0]?.id, warehouse_id: h.warehouse_id || wh.data.data[0]?.id }))
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [type])

  const setLine = (i, patch) => setLines((ls) => ls.map((l, idx) => (idx === i ? { ...l, ...patch } : l)))
  const subtotal = lines.reduce((s, l) => s + Number(l.quantity || 0) * Number(l.unit_price || 0), 0)
  const tax = lines.reduce((s, l) => s + Number(l.quantity || 0) * Number(l.unit_price || 0) * Number(l.tax_rate || 0) / 100, 0)

  const save = async () => {
    setErr(null); setMsg(null)
    try {
      await api.post('/returns', { type, ...head, lines: lines.filter((l) => l.item_id).map((l) => ({
        item_id: Number(l.item_id), quantity: Number(l.quantity || 0), unit_price: Number(l.unit_price || 0), tax_rate: Number(l.tax_rate || 0) })) })
      setMsg('تم تسجيل المرتجع وترحيله'); setLines([emptyLine()]); reload()
    } catch (e) { setErr(e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'تعذّر الحفظ') }
  }

  return (
    <div>
      <div className="toolbar">
        {[['SALES', 'مرتجع مبيعات'], ['PURCHASE', 'مرتجع مشتريات']].map(([v, ar]) => (
          <button key={v} className={`tab ${type === v ? 'active' : ''}`} onClick={() => setType(v)}>{ar}</button>
        ))}
      </div>
      <div className="card">
        <div className="row" style={{ marginBottom: 10 }}>
          <select required value={head.party_account_id || ''} onChange={(e) => setHead({ ...head, party_account_id: e.target.value })}>
            <option value="">— {type === 'SALES' ? 'العميل' : 'المورد'} —</option>{parties.map((p) => <option key={p.id} value={p.id}>{p.code} {p.name}</option>)}
          </select>
          <select value={head.warehouse_id || ''} onChange={(e) => setHead({ ...head, warehouse_id: e.target.value })}>{warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}</select>
          <select value={head.fiscal_year_id || ''} onChange={(e) => setHead({ ...head, fiscal_year_id: e.target.value })}>{years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}</select>
          <input type="date" value={head.return_date} onChange={(e) => setHead({ ...head, return_date: e.target.value })} />
        </div>
        <table>
          <thead><tr><th>الصنف</th><th>الكمية</th><th>السعر</th><th>ضريبة %</th><th>الإجمالي</th><th></th></tr></thead>
          <tbody>
            {lines.map((l, i) => (
              <tr key={i}>
                <td><select value={l.item_id} onChange={(e) => setLine(i, { item_id: e.target.value })}><option value="">— صنف —</option>{items.map((it) => <option key={it.id} value={it.id}>{it.code} {it.name}</option>)}</select></td>
                <td><input type="number" step="0.001" value={l.quantity} onChange={(e) => setLine(i, { quantity: e.target.value })} style={{ width: 80 }} /></td>
                <td><input type="number" step="0.001" value={l.unit_price} onChange={(e) => setLine(i, { unit_price: e.target.value })} style={{ width: 90 }} /></td>
                <td><input type="number" step="0.01" value={l.tax_rate} onChange={(e) => setLine(i, { tax_rate: e.target.value })} style={{ width: 70 }} /></td>
                <td>{n(Number(l.quantity || 0) * Number(l.unit_price || 0))}</td>
                <td><button className="btn-danger btn-sm" onClick={() => setLines((ls) => ls.length > 1 ? ls.filter((_, x) => x !== i) : ls)}>×</button></td>
              </tr>
            ))}
          </tbody>
          <tfoot><tr><td colSpan={4}>الإجمالي</td><td colSpan={2}>{n(subtotal + tax)}</td></tr></tfoot>
        </table>
        <div className="toolbar" style={{ marginTop: 10 }}>
          <button className="btn" onClick={() => setLines((ls) => [...ls, emptyLine()])}>+ سطر</button>
          <button className="btn-success" onClick={save}>💾 حفظ المرتجع</button>
        </div>
        {msg && <p className="ok">{msg}</p>}{err && <p className="err">{err}</p>}
      </div>
      <div className="card">
        <h4>المرتجعات</h4>
        <table>
          <thead><tr><th>الرقم</th><th>التاريخ</th><th>الإجمالي</th></tr></thead>
          <tbody>
            {list.map((r) => <tr key={r.id}><td>{r.return_number}</td><td>{String(r.return_date).slice(0, 10)}</td><td>{n(r.total)}</td></tr>)}
            {list.length === 0 && <tr><td colSpan={3} className="muted">لا توجد مرتجعات</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  )
}
