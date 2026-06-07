import { useEffect, useState } from 'react'
import api from './api.js'

const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const today = () => new Date().toISOString().slice(0, 10)

export default function Banks() {
  const [banks, setBanks] = useState([]); const [list, setList] = useState([])
  const [form, setForm] = useState({ statement_date: today(), statement_balance: '' })
  const [view, setView] = useState(null)
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const reload = async () => {
    const [ac, l] = await Promise.all([api.get('/accounts'), api.get('/bank-reconciliations')])
    setBanks(ac.data.data.filter((x) => x.is_leaf && x.is_cash_or_bank)); setList(l.data.data)
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [])

  const create = async (e) => {
    e.preventDefault(); setErr(null); setMsg(null)
    try {
      const r = await api.post('/bank-reconciliations', { ...form, statement_balance: Number(form.statement_balance) })
      setView(r.data.data); setMsg('تم إنشاء التسوية'); reload()
    } catch (e) { setErr(e.response?.data?.message || 'تعذّر الإنشاء') }
  }
  const open = async (id) => { const r = await api.get(`/bank-reconciliations/${id}`); setView(r.data.data) }
  const toggle = async (lineId, cleared) => {
    const id = view.reconciliation.id
    const r = await api.post(`/bank-reconciliations/${id}/toggle`, { line_id: lineId, cleared })
    setView(r.data.data)
  }
  const complete = async () => {
    setErr(null); setMsg(null)
    try { const r = await api.post(`/bank-reconciliations/${view.reconciliation.id}/complete`); setView(r.data.data); setMsg('تمت التسوية'); reload() }
    catch (e) { setErr(e.response?.data?.message) }
  }

  return (
    <div>
      <div className="card">
        <h4>تسوية بنكية جديدة</h4>
        <form onSubmit={create} className="row">
          <select required value={form.account_id || ''} onChange={(e) => setForm({ ...form, account_id: e.target.value })}>
            <option value="">— الحساب البنكي —</option>{banks.map((a) => <option key={a.id} value={a.id}>{a.code} {a.name}</option>)}
          </select>
          <input type="date" value={form.statement_date} onChange={(e) => setForm({ ...form, statement_date: e.target.value })} />
          <input type="number" step="0.001" placeholder="رصيد كشف البنك" required value={form.statement_balance} onChange={(e) => setForm({ ...form, statement_balance: e.target.value })} />
          <button className="btn-success" type="submit">إنشاء التسوية</button>
        </form>
        {msg && <p className="ok">{msg}</p>}
        {err && <p className="err">{err}</p>}
      </div>

      {view && (
        <div className="card">
          <div className="toolbar">
            <span className="badge badge-blue">رصيد الكشف: {n(view.reconciliation.statement_balance)}</span>
            <span className="badge badge-gray">المُسوّى: {n(view.cleared_balance)}</span>
            <span className={`badge ${view.is_reconciled ? 'badge-green' : 'badge-red'}`}>الفرق: {n(view.difference)}</span>
            {view.reconciliation.status === 'COMPLETED'
              ? <span className="badge badge-green">مكتملة ✓</span>
              : <button className="btn-success btn-sm" onClick={complete} disabled={!view.is_reconciled}>إتمام التسوية</button>}
          </div>
          <table>
            <thead><tr><th>تأشير</th><th>القيد</th><th>التاريخ</th><th>البيان</th><th>مدين</th><th>دائن</th></tr></thead>
            <tbody>
              {view.lines.map((l) => (
                <tr key={l.line_id}>
                  <td><input type="checkbox" checked={l.is_cleared} disabled={view.reconciliation.status === 'COMPLETED'} onChange={(e) => toggle(l.line_id, e.target.checked)} /></td>
                  <td>{l.entry_number}</td><td>{l.date}</td><td>{l.description}</td><td>{n(l.debit)}</td><td>{n(l.credit)}</td>
                </tr>
              ))}
              {view.lines.length === 0 && <tr><td colSpan={6} className="muted">لا توجد حركات</td></tr>}
            </tbody>
          </table>
        </div>
      )}

      <div className="card">
        <h4>التسويات السابقة</h4>
        <table>
          <thead><tr><th>#</th><th>الحساب</th><th>تاريخ الكشف</th><th>رصيد الكشف</th><th>الحالة</th><th></th></tr></thead>
          <tbody>
            {list.map((r) => (
              <tr key={r.id}>
                <td>{r.id}</td><td>{r.account?.name}</td><td>{String(r.statement_date).slice(0, 10)}</td><td>{n(r.statement_balance)}</td>
                <td><span className={`badge ${r.status === 'COMPLETED' ? 'badge-green' : 'badge-gray'}`}>{r.status === 'COMPLETED' ? 'مكتملة' : 'مسودة'}</span></td>
                <td><button className="btn btn-sm" onClick={() => open(r.id)}>فتح</button></td>
              </tr>
            ))}
            {list.length === 0 && <tr><td colSpan={6} className="muted">لا توجد تسويات</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  )
}
