import { useEffect, useState } from 'react'
import api from './api.js'

const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const today = () => new Date().toISOString().slice(0, 10)

export default function Pos() {
  const [items, setItems] = useState([]); const [warehouses, setWarehouses] = useState([]); const [cashAccs, setCashAccs] = useState([]); const [years, setYears] = useState([])
  const [cfg, setCfg] = useState({})
  const [cart, setCart] = useState([]) // {item_id, name, unit_price, quantity, tax_rate}
  const [paid, setPaid] = useState('')
  const [receipt, setReceipt] = useState(null)
  const [err, setErr] = useState(null)

  useEffect(() => {
    Promise.all([api.get('/items'), api.get('/warehouses'), api.get('/accounts'), api.get('/settings/fiscal-years')])
      .then(([it, wh, ac, y]) => {
        setItems(it.data.data); setWarehouses(wh.data.data); setYears(y.data.data)
        setCashAccs(ac.data.data.filter((x) => x.is_leaf && x.is_cash_or_bank))
        setCfg({ warehouse_id: wh.data.data[0]?.id, cash_account_id: ac.data.data.find((x) => x.is_cash_or_bank)?.id, fiscal_year_id: y.data.data[0]?.id })
      }).catch(() => setErr('تعذّر التحميل'))
  }, [])

  const addItem = (it) => {
    setCart((c) => {
      const ex = c.find((l) => l.item_id === it.id)
      if (ex) return c.map((l) => l.item_id === it.id ? { ...l, quantity: l.quantity + 1 } : l)
      return [...c, { item_id: it.id, name: it.name, unit_price: Number(it.sale_price || 0), quantity: 1, tax_rate: 0 }]
    })
  }
  const setQty = (id, q) => setCart((c) => c.map((l) => l.item_id === id ? { ...l, quantity: Number(q) } : l))
  const removeLine = (id) => setCart((c) => c.filter((l) => l.item_id !== id))

  const subtotal = cart.reduce((s, l) => s + l.quantity * l.unit_price, 0)
  const tax = cart.reduce((s, l) => s + l.quantity * l.unit_price * (l.tax_rate || 0) / 100, 0)
  const total = subtotal + tax
  const change = Math.max(0, Number(paid || 0) - total)

  const checkout = async () => {
    setErr(null); setReceipt(null)
    try {
      const r = await api.post('/pos/checkout', {
        ...cfg, sale_date: today(), paid: Number(paid || total),
        lines: cart.map((l) => ({ item_id: l.item_id, quantity: l.quantity, unit_price: l.unit_price, tax_rate: l.tax_rate || 0 })),
      })
      setReceipt(r.data.data); setCart([]); setPaid('')
    } catch (e) { setErr(e.response?.data?.message || 'تعذّر البيع') }
  }

  return (
    <div style={{ display: 'grid', gridTemplateColumns: '1fr 380px', gap: 16 }}>
      {/* الأصناف */}
      <div className="card">
        <h4>الأصناف</h4>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(130px,1fr))', gap: 10 }}>
          {items.map((it) => (
            <button key={it.id} onClick={() => addItem(it)} className="btn"
              style={{ padding: 14, display: 'flex', flexDirection: 'column', gap: 4, textAlign: 'center' }}>
              <span>📦</span><b>{it.name}</b><span className="muted">{n(it.sale_price)}</span>
            </button>
          ))}
          {items.length === 0 && <p className="muted">لا توجد أصناف</p>}
        </div>
      </div>

      {/* السلة */}
      <div className="card" style={{ position: 'sticky', top: 80, alignSelf: 'start' }}>
        <h4>الفاتورة</h4>
        <div className="row" style={{ marginBottom: 8 }}>
          <select value={cfg.warehouse_id || ''} onChange={(e) => setCfg({ ...cfg, warehouse_id: e.target.value })}>{warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}</select>
          <select value={cfg.cash_account_id || ''} onChange={(e) => setCfg({ ...cfg, cash_account_id: e.target.value })}>{cashAccs.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}</select>
        </div>
        <table>
          <tbody>
            {cart.map((l) => (
              <tr key={l.item_id}>
                <td>{l.name}</td>
                <td><input type="number" min="1" step="0.001" value={l.quantity} onChange={(e) => setQty(l.item_id, e.target.value)} style={{ width: 60 }} /></td>
                <td>{n(l.quantity * l.unit_price)}</td>
                <td><button className="btn-danger btn-sm" onClick={() => removeLine(l.item_id)}>×</button></td>
              </tr>
            ))}
            {cart.length === 0 && <tr><td className="muted">السلة فارغة — اضغط على صنف</td></tr>}
          </tbody>
        </table>
        <div style={{ marginTop: 10, fontSize: 15 }}>
          <div className="row" style={{ justifyContent: 'space-between' }}><span>الإجمالي قبل الضريبة</span><b>{n(subtotal)}</b></div>
          <div className="row" style={{ justifyContent: 'space-between' }}><span>الضريبة</span><b>{n(tax)}</b></div>
          <div className="row" style={{ justifyContent: 'space-between', fontSize: 18, color: 'var(--primary)' }}><span>الإجمالي</span><b>{n(total)}</b></div>
        </div>
        <div className="row" style={{ marginTop: 8 }}>
          <input type="number" step="0.001" placeholder="المدفوع" value={paid} onChange={(e) => setPaid(e.target.value)} style={{ flex: 1 }} />
          <span className="badge badge-amber">الباقي: {n(change)}</span>
        </div>
        <button className="btn-success" style={{ width: '100%', marginTop: 10, padding: 12, fontSize: 16 }} disabled={cart.length === 0} onClick={checkout}>💵 إتمام البيع</button>
        {err && <p className="err">{err}</p>}
        {receipt && (
          <div style={{ marginTop: 12, background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 8, padding: 12 }}>
            <b className="ok">✓ تم البيع: {receipt.sale_number}</b>
            <div>الإجمالي: {n(receipt.total)} · الباقي: {n(receipt.change_amount)}</div>
          </div>
        )}
      </div>
    </div>
  )
}
