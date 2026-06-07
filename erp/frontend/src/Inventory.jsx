import { useEffect, useState } from 'react'
import api from './api.js'

const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const today = () => new Date().toISOString().slice(0, 10)

export default function Inventory() {
  const [tab, setTab] = useState('items')
  const [items, setItems] = useState([]); const [warehouses, setWarehouses] = useState([]); const [valuation, setValuation] = useState(null)
  const [itemForm, setItemForm] = useState({}); const [whForm, setWhForm] = useState({})
  const [op, setOp] = useState({ type: 'receive', movement_date: today() })
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const reload = async () => {
    const [it, wh] = await Promise.all([api.get('/items'), api.get('/warehouses')])
    setItems(it.data.data); setWarehouses(wh.data.data)
    if (tab === 'valuation') setValuation((await api.get('/stock/valuation')).data.data)
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [tab])

  const wrap = async (fn) => { setErr(null); setMsg(null); try { await fn(); reload() } catch (e) { setErr(e.response?.data?.message || 'خطأ') } }
  const addItem = (e) => { e.preventDefault(); wrap(async () => { await api.post('/items', itemForm); setItemForm({}); setMsg('تم حفظ الصنف') }) }
  const addWh = (e) => { e.preventDefault(); wrap(async () => { await api.post('/warehouses', whForm); setWhForm({}); setMsg('تم حفظ المخزن') }) }
  const doOp = (e) => {
    e.preventDefault()
    wrap(async () => {
      const base = { item_id: Number(op.item_id), movement_date: op.movement_date }
      if (op.type === 'receive') await api.post('/stock/receive', { ...base, warehouse_id: op.warehouse_id, quantity: Number(op.quantity), unit_cost: Number(op.unit_cost || 0) })
      if (op.type === 'issue') await api.post('/stock/issue', { ...base, warehouse_id: op.warehouse_id, quantity: Number(op.quantity) })
      if (op.type === 'transfer') await api.post('/stock/transfer', { ...base, from_warehouse_id: op.from_warehouse_id, to_warehouse_id: op.to_warehouse_id, quantity: Number(op.quantity) })
      if (op.type === 'adjust') await api.post('/stock/adjust', { ...base, warehouse_id: op.warehouse_id, delta: Number(op.delta) })
      setOp({ type: op.type, movement_date: today() }); setMsg('تمت الحركة')
    })
  }

  return (
    <div>
      <div className="toolbar">
        {[['items', 'الأصناف'], ['warehouses', 'المخازن'], ['operations', 'الحركات'], ['valuation', 'التقييم']].map(([k, l]) => (
          <button key={k} className={`tab ${tab === k ? 'active' : ''}`} onClick={() => setTab(k)}>{l}</button>
        ))}
      </div>
      {msg && <p className="ok">{msg}</p>}{err && <p className="err">{err}</p>}

      {tab === 'items' && (
        <>
          <div className="card"><h4>إضافة صنف</h4>
            <form onSubmit={addItem} className="row">
              <input placeholder="الرمز" required value={itemForm.code || ''} onChange={(e) => setItemForm({ ...itemForm, code: e.target.value })} />
              <input placeholder="الاسم" required value={itemForm.name || ''} onChange={(e) => setItemForm({ ...itemForm, name: e.target.value })} />
              <input placeholder="الوحدة" value={itemForm.unit || ''} onChange={(e) => setItemForm({ ...itemForm, unit: e.target.value })} />
              <input type="number" step="0.001" placeholder="سعر الشراء" value={itemForm.purchase_price || ''} onChange={(e) => setItemForm({ ...itemForm, purchase_price: e.target.value })} />
              <input type="number" step="0.001" placeholder="سعر البيع" value={itemForm.sale_price || ''} onChange={(e) => setItemForm({ ...itemForm, sale_price: e.target.value })} />
              <button className="btn-success" type="submit">+ إضافة</button>
            </form>
          </div>
          <div className="card"><table>
            <thead><tr><th>الرمز</th><th>الاسم</th><th>الوحدة</th><th>متوسط التكلفة</th><th>سعر البيع</th></tr></thead>
            <tbody>{items.map((i) => <tr key={i.id}><td>{i.code}</td><td>{i.name}</td><td>{i.unit}</td><td>{n(i.average_cost)}</td><td>{n(i.sale_price)}</td></tr>)}
              {items.length === 0 && <tr><td colSpan={5} className="muted">لا توجد أصناف</td></tr>}</tbody>
          </table></div>
        </>
      )}

      {tab === 'warehouses' && (
        <>
          <div className="card"><h4>إضافة مخزن</h4>
            <form onSubmit={addWh} className="row">
              <input placeholder="الرمز" required value={whForm.code || ''} onChange={(e) => setWhForm({ ...whForm, code: e.target.value })} />
              <input placeholder="الاسم" required value={whForm.name || ''} onChange={(e) => setWhForm({ ...whForm, name: e.target.value })} />
              <button className="btn-success" type="submit">+ إضافة</button>
            </form>
          </div>
          <div className="card"><table>
            <thead><tr><th>الرمز</th><th>الاسم</th></tr></thead>
            <tbody>{warehouses.map((w) => <tr key={w.id}><td>{w.code}</td><td>{w.name}</td></tr>)}
              {warehouses.length === 0 && <tr><td colSpan={2} className="muted">لا توجد مخازن</td></tr>}</tbody>
          </table></div>
        </>
      )}

      {tab === 'operations' && (
        <div className="card"><h4>حركة مخزنية</h4>
          <form onSubmit={doOp} className="row">
            <select value={op.type} onChange={(e) => setOp({ ...op, type: e.target.value })}>
              <option value="receive">استلام</option><option value="issue">صرف</option><option value="transfer">تحويل</option><option value="adjust">تسوية</option>
            </select>
            <select required value={op.item_id || ''} onChange={(e) => setOp({ ...op, item_id: e.target.value })}>
              <option value="">— الصنف —</option>{items.map((i) => <option key={i.id} value={i.id}>{i.code} {i.name}</option>)}
            </select>
            {op.type !== 'transfer' && <select required value={op.warehouse_id || ''} onChange={(e) => setOp({ ...op, warehouse_id: e.target.value })}>
              <option value="">— المخزن —</option>{warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}</select>}
            {op.type === 'transfer' && <>
              <select required value={op.from_warehouse_id || ''} onChange={(e) => setOp({ ...op, from_warehouse_id: e.target.value })}><option value="">— من —</option>{warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}</select>
              <select required value={op.to_warehouse_id || ''} onChange={(e) => setOp({ ...op, to_warehouse_id: e.target.value })}><option value="">— إلى —</option>{warehouses.map((w) => <option key={w.id} value={w.id}>{w.name}</option>)}</select>
            </>}
            {op.type === 'adjust'
              ? <input type="number" step="0.001" placeholder="الفرق (+/-)" required value={op.delta || ''} onChange={(e) => setOp({ ...op, delta: e.target.value })} />
              : <input type="number" step="0.001" placeholder="الكمية" required value={op.quantity || ''} onChange={(e) => setOp({ ...op, quantity: e.target.value })} />}
            {op.type === 'receive' && <input type="number" step="0.001" placeholder="تكلفة الوحدة" required value={op.unit_cost || ''} onChange={(e) => setOp({ ...op, unit_cost: e.target.value })} />}
            <input type="date" value={op.movement_date} onChange={(e) => setOp({ ...op, movement_date: e.target.value })} />
            <button className="btn-success" type="submit">تنفيذ</button>
          </form>
        </div>
      )}

      {tab === 'valuation' && valuation && (
        <div className="card"><table>
          <thead><tr><th>الرمز</th><th>الصنف</th><th>الكمية</th><th>متوسط التكلفة</th><th>القيمة</th></tr></thead>
          <tbody>
            {valuation.rows.map((r) => <tr key={r.item_id}><td>{r.code}</td><td>{r.name} {r.below_reorder && <span className="badge badge-amber">تحت حد الطلب</span>}</td><td>{n(r.quantity)}</td><td>{n(r.average_cost)}</td><td>{n(r.value)}</td></tr>)}
          </tbody>
          <tfoot><tr><td colSpan={4}>إجمالي قيمة المخزون</td><td>{n(valuation.total_value)}</td></tr></tfoot>
        </table></div>
      )}
    </div>
  )
}
