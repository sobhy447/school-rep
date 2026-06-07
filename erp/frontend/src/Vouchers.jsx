import { useEffect, useState } from 'react'
import api from './api.js'
import AttachmentsModal from './AttachmentsModal.jsx'

const emptyLine = () => ({ account_id: '', amount: '', cost_center_id: '', cost_center_extra_id: '' })
const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const typeAr = (t) => ({ RECEIPT: 'قبض', PAYMENT: 'صرف', TRANSFER: 'تحويل' }[t] || t)

export default function Vouchers() {
  const [type, setType] = useState('PAYMENT')
  const [years, setYears] = useState([]); const [accounts, setAccounts] = useState([]); const [cashAccounts, setCashAccounts] = useState([])
  const [costCenters, setCostCenters] = useState([]); const [list, setList] = useState([])
  const [head, setHead] = useState({ entry_date: new Date().toISOString().slice(0, 10), party_name: '', description: '' })
  const [main, setMain] = useState({ account_id: '', amount: '' }); const [lines, setLines] = useState([emptyLine()])
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null); const [attachEntry, setAttachEntry] = useState(null)

  const reload = async () => {
    const [y, a, c, v] = await Promise.all([api.get('/settings/fiscal-years'), api.get('/accounts'), api.get('/settings/cost-centers'), api.get('/vouchers')])
    setYears(y.data.data); setList(v.data.data)
    const leaves = a.data.data.filter((x) => x.is_leaf); setAccounts(leaves); setCashAccounts(leaves.filter((x) => x.is_cash_or_bank)); setCostCenters(c.data.data)
    if (y.data.data[0]) setHead((h) => ({ ...h, fiscal_year_id: h.fiscal_year_id || y.data.data[0].id }))
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [])

  const mainLabel = type === 'RECEIPT' ? 'المقبوض في (نقدية/بنك) — مدين' : 'المصروف من (نقدية/بنك) — دائن'
  const linesTotal = lines.reduce((s, l) => s + Number(l.amount || 0), 0)
  const ok = Number(main.amount || 0) > 0 && Math.abs(linesTotal - Number(main.amount || 0)) < 0.0005 && main.account_id

  const setLine = (i, patch) => setLines((ls) => ls.map((l, idx) => (idx === i ? { ...l, ...patch } : l)))
  const onExtra = (i, ccId) => { const cc = costCenters.find((c) => String(c.id) === String(ccId)); const patch = { cost_center_extra_id: ccId }; if (cc?.linked_account_id && !lines[i].account_id) patch.account_id = cc.linked_account_id; setLine(i, patch) }

  const save = async () => {
    setErr(null); setMsg(null)
    try {
      const payload = { type, ...head, main: { account_id: main.account_id, amount: Number(main.amount) },
        lines: lines.filter((l) => l.account_id || l.cost_center_extra_id).map((l) => ({
          account_id: l.account_id || null, debit: type === 'PAYMENT' ? Number(l.amount || 0) : 0, credit: type === 'RECEIPT' ? Number(l.amount || 0) : 0,
          cost_center_id: l.cost_center_id || null, cost_center_extra_id: l.cost_center_extra_id || null })) }
      const r = await api.post('/vouchers', payload)
      setMsg(`تم حفظ السند وترحيله: ${r.data.data.entry_number}`); setMain({ account_id: '', amount: '' }); setLines([emptyLine()]); setHead((h) => ({ ...h, party_name: '', description: '' })); reload()
    } catch (e) { setErr(e.response?.data?.message || 'تعذّر الحفظ') }
  }

  return (
    <div>
      <div className="toolbar">
        {[['PAYMENT', '🔻 سند صرف'], ['RECEIPT', '🔺 سند قبض']].map(([v, ar]) => (
          <button key={v} className={`tab ${type === v ? 'active' : ''}`} onClick={() => setType(v)}>{ar}</button>
        ))}
      </div>

      <div className="card">
        <div className="row" style={{ marginBottom: 12 }}>
          <select value={head.fiscal_year_id || ''} onChange={(e) => setHead({ ...head, fiscal_year_id: e.target.value })}>{years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}</select>
          <input type="date" value={head.entry_date} onChange={(e) => setHead({ ...head, entry_date: e.target.value })} />
          <input placeholder={type === 'RECEIPT' ? 'المقبوض منه' : 'المصروف له'} value={head.party_name} onChange={(e) => setHead({ ...head, party_name: e.target.value })} />
          <input style={{ flex: 1, minWidth: 180 }} placeholder="البيان" value={head.description} onChange={(e) => setHead({ ...head, description: e.target.value })} />
        </div>
        <div className="row" style={{ background: 'var(--primary-50)', padding: 12, borderRadius: 10 }}>
          <b style={{ minWidth: 230, color: 'var(--primary)' }}>{mainLabel}</b>
          <select value={main.account_id} onChange={(e) => setMain({ ...main, account_id: e.target.value })}>
            <option value="">— حساب نقدية/بنك —</option>{cashAccounts.map((a) => <option key={a.id} value={a.id}>{a.code} {a.name}</option>)}
          </select>
          <input type="number" step="0.001" placeholder="المبلغ" value={main.amount} onChange={(e) => setMain({ ...main, amount: e.target.value })} />
        </div>
      </div>

      <div className="card">
        <h4>{type === 'RECEIPT' ? 'الأطراف الدائنة' : 'الأطراف المدينة'}</h4>
        <table>
          <thead><tr><th>الحساب</th><th>المبلغ</th><th>مركز أساسي</th><th>مركز إضافي</th><th></th></tr></thead>
          <tbody>
            {lines.map((l, i) => (
              <tr key={i}>
                <td><select value={l.account_id} onChange={(e) => setLine(i, { account_id: e.target.value })}><option value="">— حساب —</option>{accounts.map((a) => <option key={a.id} value={a.id}>{a.code} {a.name}</option>)}</select></td>
                <td><input type="number" step="0.001" value={l.amount} onChange={(e) => setLine(i, { amount: e.target.value })} style={{ width: 100 }} /></td>
                <td><select value={l.cost_center_id} onChange={(e) => setLine(i, { cost_center_id: e.target.value })}><option value="">—</option>{costCenters.map((c) => <option key={c.id} value={c.id}>{c.code} {c.name}</option>)}</select></td>
                <td><select value={l.cost_center_extra_id} onChange={(e) => onExtra(i, e.target.value)}><option value="">—</option>{costCenters.map((c) => <option key={c.id} value={c.id}>{c.code} {c.name}</option>)}</select></td>
                <td><button className="btn-danger btn-sm" onClick={() => setLines((ls) => ls.length > 1 ? ls.filter((_, x) => x !== i) : ls)}>×</button></td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="toolbar" style={{ marginTop: 10 }}>
          <button className="btn" onClick={() => setLines((ls) => [...ls, emptyLine()])}>+ سطر</button>
          <button className="btn-success" onClick={save} disabled={!ok}>💾 حفظ وترحيل</button>
          <span className={`badge ${ok ? 'badge-green' : 'badge-red'}`}>الأطراف {n(linesTotal)} / الرئيسي {n(main.amount || 0)}</span>
        </div>
        {msg && <p className="ok">{msg}</p>}
        {err && <p className="err">{err}</p>}
      </div>

      <div className="card">
        <h4>السندات الأخيرة</h4>
        <table>
          <thead><tr><th>الرقم</th><th>النوع</th><th>التاريخ</th><th>الطرف</th><th>المبلغ</th><th>الحالة</th><th>مرفقات</th></tr></thead>
          <tbody>
            {list.map((v) => (
              <tr key={v.id}><td>{v.entry_number}</td><td>{typeAr(v.type)}</td><td>{String(v.entry_date).slice(0, 10)}</td><td>{v.party_name}</td><td>{n(v.total_debit)}</td><td><span className="badge badge-green">مرحّل</span></td><td><button className="btn btn-sm" onClick={() => setAttachEntry(v.id)}>📎</button></td></tr>
            ))}
            {list.length === 0 && <tr><td colSpan={7} className="muted">لا توجد سندات</td></tr>}
          </tbody>
        </table>
      </div>
      {attachEntry && <AttachmentsModal entryId={attachEntry} onClose={() => setAttachEntry(null)} />}
    </div>
  )
}
