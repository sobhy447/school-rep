import { useEffect, useRef, useState } from 'react'
import api, { openPdf } from './api.js'
import AttachmentsModal from './AttachmentsModal.jsx'
import { SkeletonTable } from './Skeleton.jsx'

const emptyLine = () => ({ account_id: '', debit: '', credit: '', cost_center_id: '', cost_center_extra_id: '', reference_number: '', description: '', counterparty_name: '' })
const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const statusBadge = (s) => ({ DRAFT: 'badge-gray', APPROVED: 'badge-blue', POSTED: 'badge-green', LOCKED: 'badge-amber' }[s] || 'badge-gray')
const statusAr = (s) => ({ DRAFT: 'مسودة', APPROVED: 'معتمد', POSTED: 'مرحّل', LOCKED: 'مقفل' }[s] || s)

export default function JournalEntry() {
  const [years, setYears] = useState([]); const [branches, setBranches] = useState([])
  const [accounts, setAccounts] = useState([]); const [costCenters, setCostCenters] = useState([]); const [list, setList] = useState([])
  const [header, setHeader] = useState({ entry_date: new Date().toISOString().slice(0, 10), description: '', currency_code: 'KWD', exchange_rate: 1 })
  const [lines, setLines] = useState([emptyLine(), emptyLine()])
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)
  const [attachEntry, setAttachEntry] = useState(null)
  const [loading, setLoading] = useState(true)
  const lineRefs = useRef([])

  const reload = async () => {
    const [y, b, a, c, l] = await Promise.all([
      api.get('/settings/fiscal-years'), api.get('/settings/branches'),
      api.get('/accounts'), api.get('/settings/cost-centers'), api.get('/journal-entries')])
    setYears(y.data.data); setBranches(b.data.data); setList(l.data.data)
    setAccounts(a.data.data.filter((x) => x.is_leaf)); setCostCenters(c.data.data)
    if (y.data.data[0]) setHeader((h) => ({ ...h, fiscal_year_id: h.fiscal_year_id || y.data.data[0].id }))
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')).finally(() => setLoading(false)) }, [])

  const totalDebit = lines.reduce((s, l) => s + Number(l.debit || 0), 0)
  const totalCredit = lines.reduce((s, l) => s + Number(l.credit || 0), 0)
  const balanced = Math.abs(totalDebit - totalCredit) < 0.0005 && totalDebit > 0

  const setLine = (i, patch) => setLines((ls) => ls.map((l, idx) => (idx === i ? { ...l, ...patch } : l)))
  const onHeaderDesc = (v) => { setHeader({ ...header, description: v }); setLines((ls) => ls.map((l, i) => (i === 0 && !l.description ? { ...l, description: v } : l))) }
  const onExtraChange = (i, ccId) => {
    const cc = costCenters.find((c) => String(c.id) === String(ccId)); const patch = { cost_center_extra_id: ccId }
    if (cc?.linked_account_id && !lines[i].account_id) patch.account_id = cc.linked_account_id
    if (cc?.counterparty_name) patch.counterparty_name = cc.counterparty_name
    setLine(i, patch)
  }
  const reset = () => { setLines([emptyLine(), emptyLine()]); setHeader((h) => ({ ...h, description: '', entry_number: '' })); setMsg(null); setErr(null) }

  const save = async () => {
    setErr(null); setMsg(null)
    try {
      const payload = { ...header, lines: lines.filter((l) => l.account_id || l.cost_center_extra_id).map((l) => ({
        account_id: l.account_id || null, debit: Number(l.debit || 0), credit: Number(l.credit || 0),
        cost_center_id: l.cost_center_id || null, cost_center_extra_id: l.cost_center_extra_id || null,
        reference_number: l.reference_number, description: l.description })) }
      const r = await api.post('/journal-entries', payload)
      setMsg(`تم حفظ القيد: ${r.data.data.entry_number}`); reset(); reload()
    } catch (e) { setErr(e.response?.data?.message || 'تعذّر الحفظ') }
  }
  const action = async (id, act) => { try { await api.post(`/journal-entries/${id}/${act}`); reload() } catch (e) { setErr(e.response?.data?.message) } }

  return (
    <div>
      <div className="card">
        <div className="toolbar">
          <button className="btn" onClick={reset}>📄 جديد</button>
          <button className="btn-success" onClick={save} disabled={!balanced}>💾 حفظ</button>
          <span className={`badge ${balanced ? 'badge-green' : 'badge-red'}`}>مدين {n(totalDebit)} / دائن {n(totalCredit)} {balanced ? '✓ متوازن' : '✗ غير متوازن'}</span>
        </div>
        <div className="row">
          <input placeholder="رقم القيد (تلقائي)" value={header.entry_number || ''} onChange={(e) => setHeader({ ...header, entry_number: e.target.value })} style={{ width: 150 }} />
          <select value={header.fiscal_year_id || ''} onChange={(e) => setHeader({ ...header, fiscal_year_id: e.target.value })}>{years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}</select>
          <input type="date" value={header.entry_date} onChange={(e) => setHeader({ ...header, entry_date: e.target.value })} />
          <select value={header.branch_id || ''} onChange={(e) => setHeader({ ...header, branch_id: e.target.value })}><option value="">— الفرع —</option>{branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}</select>
          <input style={{ width: 70 }} placeholder="عملة" value={header.currency_code} onChange={(e) => setHeader({ ...header, currency_code: e.target.value })} />
          <input style={{ flex: 1, minWidth: 200 }} placeholder="البيان (ينسخ للسطر الأول)" value={header.description} onChange={(e) => onHeaderDesc(e.target.value)} />
        </div>
      </div>

      <div className="card">
        <table>
          <thead><tr><th>الحساب (F1)</th><th>مدين</th><th>دائن</th><th>مركز أساسي</th><th>مركز إضافي</th><th>مرجع</th><th>البيان</th><th>اسم الخصم</th><th></th></tr></thead>
          <tbody>
            {lines.map((l, i) => (
              <tr key={i}>
                <td><input list="acc-list" ref={(el) => (lineRefs.current[i] = el)} onKeyDown={(e) => { if (e.key === 'F1') { e.preventDefault(); lineRefs.current[i]?.focus() } }}
                  value={accDisplay(l.account_id, accounts)} onChange={(e) => setLine(i, { account_id: accId(e.target.value, accounts) })} placeholder="رقم/اسم الحساب" style={{ width: 160 }} /></td>
                <td><input type="number" step="0.001" value={l.debit} onChange={(e) => setLine(i, { debit: e.target.value, credit: '' })} style={{ width: 90 }} /></td>
                <td><input type="number" step="0.001" value={l.credit} onChange={(e) => setLine(i, { credit: e.target.value, debit: '' })} style={{ width: 90 }} /></td>
                <td><select value={l.cost_center_id} onChange={(e) => setLine(i, { cost_center_id: e.target.value })}><option value="">—</option>{costCenters.map((c) => <option key={c.id} value={c.id}>{c.code} {c.name}</option>)}</select></td>
                <td><select value={l.cost_center_extra_id} onChange={(e) => onExtraChange(i, e.target.value)}><option value="">—</option>{costCenters.map((c) => <option key={c.id} value={c.id}>{c.code} {c.name}</option>)}</select></td>
                <td><input value={l.reference_number} onChange={(e) => setLine(i, { reference_number: e.target.value })} style={{ width: 70 }} /></td>
                <td><input value={l.description} onChange={(e) => setLine(i, { description: e.target.value })} style={{ width: 140 }} /></td>
                <td className="muted">{l.counterparty_name}</td>
                <td><button className="btn-danger btn-sm" onClick={() => setLines((ls) => ls.length > 1 ? ls.filter((_, x) => x !== i) : ls)}>×</button></td>
              </tr>
            ))}
          </tbody>
        </table>
        <datalist id="acc-list">{accounts.map((a) => <option key={a.id} value={`${a.code} — ${a.name}`} />)}</datalist>
        <button className="btn" style={{ marginTop: 10 }} onClick={() => setLines((ls) => [...ls, emptyLine()])}>+ سطر</button>
        {msg && <p className="ok">{msg}</p>}
        {err && <p className="err">{err}</p>}
      </div>

      <div className="card">
        <h4>القيود الأخيرة</h4>
        {loading ? <SkeletonTable cols={6} rows={5} /> : (
        <table>
          <thead><tr><th>الرقم</th><th>التاريخ</th><th>البيان</th><th>مدين</th><th>الحالة</th><th>إجراءات</th></tr></thead>
          <tbody>
            {list.map((e) => (
              <tr key={e.id}>
                <td>{e.entry_number}</td><td>{String(e.entry_date).slice(0, 10)}</td><td>{e.description}</td><td>{n(e.total_debit)}</td>
                <td><span className={`badge ${statusBadge(e.status)}`}>{statusAr(e.status)}</span></td>
                <td>
                  {e.status === 'DRAFT' && <button className="btn-primary btn-sm" onClick={() => action(e.id, 'approve')}>اعتماد</button>}
                  {e.status === 'APPROVED' && <button className="btn-primary btn-sm" onClick={() => action(e.id, 'post')}>ترحيل</button>}
                  {e.status === 'POSTED' && <button className="btn btn-sm" onClick={() => action(e.id, 'reverse')}>عكس</button>}
                  <button className="btn btn-sm" onClick={() => setAttachEntry(e.id)}>📎</button>{' '}
                  <button className="btn btn-sm" onClick={() => openPdf(`/journal-entries/${e.id}/pdf`)}>🖨</button>
                </td>
              </tr>
            ))}
            {list.length === 0 && <tr><td colSpan={6} className="muted">لا توجد قيود</td></tr>}
          </tbody>
        </table>
        )}
      </div>
      {attachEntry && <AttachmentsModal entryId={attachEntry} onClose={() => setAttachEntry(null)} />}
    </div>
  )
}

const accDisplay = (id, accounts) => { const a = accounts.find((x) => String(x.id) === String(id)); return a ? `${a.code} — ${a.name}` : '' }
const accId = (val, accounts) => { const a = accounts.find((x) => `${x.code} — ${x.name}` === val || String(x.code) === val); return a ? a.id : '' }
