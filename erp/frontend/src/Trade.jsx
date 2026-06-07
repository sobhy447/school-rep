import { useEffect, useState } from 'react'
import api, { openPdf } from './api.js'

const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const today = () => new Date().toISOString().slice(0, 10)
const emptyLine = () => ({ item_id: '', quantity: '', unit_price: '', tax_rate: '' })

const CONF = {
  purchases: { ep: 'purchase-invoices', partyField: 'vendor_account_id', partyType: 'VENDOR', partyLabel: 'المورد', priceLabel: 'تكلفة الوحدة', title: 'فاتورة مشتريات' },
  sales: { ep: 'sales-invoices', partyField: 'customer_account_id', partyType: 'CUSTOMER', partyLabel: 'العميل', priceLabel: 'سعر البيع', title: 'فاتورة مبيعات' },
}

function InvoiceManager({ kind }) {
  const c = CONF[kind]
  const [parties, setParties] = useState([]); const [items, setItems] = useState([]); const [warehouses, setWarehouses] = useState([]); const [years, setYears] = useState([])
  const [list, setList] = useState([])
  const [head, setHead] = useState({ invoice_date: today() })
  const [lines, setLines] = useState([emptyLine()])
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const reload = async () => {
    const [p, it, wh, y, l] = await Promise.all([
      api.get(`/accounts/parties?type=${c.partyType}`), api.get('/items'), api.get('/warehouses'), api.get('/settings/fiscal-years'), api.get(`/${c.ep}`)])
    setParties(p.data.data); setItems(it.data.data); setWarehouses(wh.data.data); setYears(y.data.data); setList(l.data.data)
    setHead((h) => ({ ...h, fiscal_year_id: h.fiscal_year_id || y.data.data[0]?.id, warehouse_id: h.warehouse_id || wh.data.data[0]?.id }))
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [kind])

  const setLine = (i, patch) => setLines((ls) => ls.map((l, idx) => (idx === i ? { ...l, ...patch } : l)))
  const onItem = (i, itemId) => {
    const it = items.find((x) => String(x.id) === String(itemId))
    setLine(i, { item_id: itemId, unit_price: kind === 'sales' ? (it?.sale_price || '') : (it?.purchase_price || '') })
  }
  const subtotal = lines.reduce((s, l) => s + Number(l.quantity || 0) * Number(l.unit_price || 0), 0)
  const tax = lines.reduce((s, l) => s + Number(l.quantity || 0) * Number(l.unit_price || 0) * Number(l.tax_rate || 0) / 100, 0)

  const save = async () => {
    setErr(null); setMsg(null)
    try {
      const payload = { ...head, [c.partyField]: head.party_id, lines: lines.filter((l) => l.item_id).map((l) => ({
        item_id: Number(l.item_id), quantity: Number(l.quantity || 0), unit_price: Number(l.unit_price || 0), tax_rate: Number(l.tax_rate || 0) })) }
      delete payload.party_id
      const r = await api.post(`/${c.ep}`, payload)
      setMsg(`تم حفظ ${c.title}: ${r.data.data.invoice_number}`); setLines([emptyLine()]); reload()
    } catch (e) { setErr(e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'تعذّر الحفظ') }
  }
  const post = async (id) => { try { await api.post(`/${c.ep}/${id}/post`); setMsg('تم ترحيل الفاتورة (مخزون + قيد)'); reload() } catch (e) { setErr(e.response?.data?.message) } }

  return (
    <div>
      <div className="card">
        <h4>{c.title} جديدة</h4>
        <div className="row" style={{ marginBottom: 10 }}>
          <select required value={head.party_id || ''} onChange={(e) => setHead({ ...head, party_id: e.target.value })}>
            <option value="">— {c.partyLabel} —</option>{parties.map((p) => <option key={p.id} value={p.id}>{p.code} {p.name}</option>)}
          </select>
          <select value={head.warehouse_id || ''} onChange={(e) => setHead({ ...head, warehouse_id: e.target.value })}>
            {warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}
          </select>
          <select value={head.fiscal_year_id || ''} onChange={(e) => setHead({ ...head, fiscal_year_id: e.target.value })}>
            {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
          </select>
          <input type="date" value={head.invoice_date} onChange={(e) => setHead({ ...head, invoice_date: e.target.value })} />
        </div>
        <table>
          <thead><tr><th>الصنف</th><th>الكمية</th><th>{c.priceLabel}</th><th>ضريبة %</th><th>الإجمالي</th><th></th></tr></thead>
          <tbody>
            {lines.map((l, i) => (
              <tr key={i}>
                <td><select value={l.item_id} onChange={(e) => onItem(i, e.target.value)}><option value="">— صنف —</option>{items.map((it) => <option key={it.id} value={it.id}>{it.code} {it.name}</option>)}</select></td>
                <td><input type="number" step="0.001" value={l.quantity} onChange={(e) => setLine(i, { quantity: e.target.value })} style={{ width: 80 }} /></td>
                <td><input type="number" step="0.001" value={l.unit_price} onChange={(e) => setLine(i, { unit_price: e.target.value })} style={{ width: 90 }} /></td>
                <td><input type="number" step="0.01" value={l.tax_rate} onChange={(e) => setLine(i, { tax_rate: e.target.value })} style={{ width: 70 }} /></td>
                <td>{n(Number(l.quantity || 0) * Number(l.unit_price || 0))}</td>
                <td><button className="btn-danger btn-sm" onClick={() => setLines((ls) => ls.length > 1 ? ls.filter((_, x) => x !== i) : ls)}>×</button></td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr><td colSpan={4}>الإجمالي قبل الضريبة</td><td colSpan={2}>{n(subtotal)}</td></tr>
            <tr><td colSpan={4}>الضريبة</td><td colSpan={2}>{n(tax)}</td></tr>
            <tr><td colSpan={4}>الإجمالي</td><td colSpan={2}>{n(subtotal + tax)}</td></tr>
          </tfoot>
        </table>
        <div className="toolbar" style={{ marginTop: 10 }}>
          <button className="btn" onClick={() => setLines((ls) => [...ls, emptyLine()])}>+ سطر</button>
          <button className="btn-success" onClick={save}>💾 حفظ</button>
        </div>
        {msg && <p className="ok">{msg}</p>}{err && <p className="err">{err}</p>}
      </div>

      <div className="card">
        <h4>الفواتير</h4>
        <table>
          <thead><tr><th>الرقم</th><th>التاريخ</th><th>الإجمالي</th><th>الحالة</th><th>إجراءات</th></tr></thead>
          <tbody>
            {list.map((v) => (
              <tr key={v.id}>
                <td>{v.invoice_number}</td><td>{String(v.invoice_date).slice(0, 10)}</td><td>{n(v.total)}</td>
                <td><span className={`badge ${v.status === 'POSTED' ? 'badge-green' : 'badge-gray'}`}>{v.status === 'POSTED' ? 'مرحّلة' : 'مسودة'}</span></td>
                <td>
                  {v.status === 'DRAFT' && <button className="btn-primary btn-sm" onClick={() => post(v.id)}>ترحيل</button>}{' '}
                  <button className="btn btn-sm" onClick={() => openPdf(`/${c.ep}/${v.id}/pdf`)}>🖨 PDF</button>
                </td>
              </tr>
            ))}
            {list.length === 0 && <tr><td colSpan={5} className="muted">لا توجد فواتير</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  )
}

export function Purchases() { return <InvoiceManager kind="purchases" /> }
export function Sales() { return <InvoiceManager kind="sales" /> }
