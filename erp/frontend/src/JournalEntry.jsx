import { useEffect, useRef, useState } from 'react'
import api from './api.js'

// شاشة القيد اليومي: رأس + أسطر غير محدودة + بحث حسابات (F1) + توازن حيّ.
const emptyLine = () => ({ account_id: '', debit: '', credit: '', cost_center_id: '', cost_center_extra_id: '', reference_number: '', description: '', counterparty_name: '' })

export default function JournalEntry() {
  const [years, setYears] = useState([])
  const [branches, setBranches] = useState([])
  const [accounts, setAccounts] = useState([])
  const [costCenters, setCostCenters] = useState([])
  const [list, setList] = useState([])
  const [header, setHeader] = useState({ entry_date: new Date().toISOString().slice(0, 10), description: '', currency_code: 'KWD', exchange_rate: 1 })
  const [lines, setLines] = useState([emptyLine(), emptyLine()])
  const [msg, setMsg] = useState(null)
  const [err, setErr] = useState(null)
  const lineRefs = useRef([])

  const reload = async () => {
    const [y, b, a, c, l] = await Promise.all([
      api.get('/settings/fiscal-years'), api.get('/settings/branches'),
      api.get('/accounts'), api.get('/settings/cost-centers'), api.get('/journal-entries'),
    ])
    setYears(y.data.data); setBranches(b.data.data); setList(l.data.data)
    setAccounts(a.data.data.filter((x) => x.is_leaf))
    setCostCenters(c.data.data)
    if (y.data.data[0]) setHeader((h) => ({ ...h, fiscal_year_id: h.fiscal_year_id || y.data.data[0].id }))
  }
  useEffect(() => { reload().catch((e) => setErr('تعذّر التحميل')) }, [])

  const totalDebit = lines.reduce((s, l) => s + Number(l.debit || 0), 0)
  const totalCredit = lines.reduce((s, l) => s + Number(l.credit || 0), 0)
  const balanced = Math.abs(totalDebit - totalCredit) < 0.0005 && totalDebit > 0

  const setLine = (i, patch) => {
    setLines((ls) => {
      const copy = ls.map((l, idx) => (idx === i ? { ...l, ...patch } : l))
      // نسخ بيان الرأس للسطر الأول تلقائياً إن كان فارغاً
      return copy
    })
  }

  // نسخ بيان الرأس لأول سطر تلقائياً
  const onHeaderDesc = (v) => {
    setHeader({ ...header, description: v })
    setLines((ls) => ls.map((l, i) => (i === 0 && !l.description ? { ...l, description: v } : l)))
  }

  const addLine = () => setLines((ls) => [...ls, emptyLine()])
  const removeLine = (i) => setLines((ls) => ls.length > 1 ? ls.filter((_, idx) => idx !== i) : ls)

  // عند كتابة مركز تكلفة إضافي بدون حساب: استدعاء الحساب المرتبط + اسم الخصم
  const onExtraChange = (i, ccId) => {
    const cc = costCenters.find((c) => String(c.id) === String(ccId))
    const patch = { cost_center_extra_id: ccId }
    if (cc && cc.linked_account_id && !lines[i].account_id) patch.account_id = cc.linked_account_id
    if (cc && cc.counterparty_name) patch.counterparty_name = cc.counterparty_name
    setLine(i, patch)
  }

  const reset = () => { setLines([emptyLine(), emptyLine()]); setHeader((h) => ({ ...h, description: '', entry_number: '' })); setMsg(null); setErr(null) }

  const save = async () => {
    setErr(null); setMsg(null)
    try {
      const payload = {
        ...header,
        lines: lines.filter((l) => l.account_id || l.cost_center_extra_id).map((l) => ({
          account_id: l.account_id || null,
          debit: Number(l.debit || 0), credit: Number(l.credit || 0),
          cost_center_id: l.cost_center_id || null,
          cost_center_extra_id: l.cost_center_extra_id || null,
          reference_number: l.reference_number, description: l.description,
        })),
      }
      const r = await api.post('/journal-entries', payload)
      setMsg(`تم حفظ القيد: ${r.data.data.entry_number}`)
      reset(); reload()
    } catch (e) {
      setErr(e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'تعذّر الحفظ')
    }
  }

  const action = async (id, act) => {
    try { await api.post(`/journal-entries/${id}/${act}`); reload() } catch (e) { setErr(e.response?.data?.message) }
  }

  return (
    <div style={{ maxWidth: 1100, margin: '20px auto', fontFamily: 'system-ui' }}>
      <h3>قيد يومية</h3>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 6 }}>
        <button onClick={reset} style={btn('#6b7280')}>جديد</button>
        <button onClick={save} disabled={!balanced} style={btn(balanced ? '#16a34a' : '#9ca3af')}>حفظ</button>
        <span style={{ alignSelf: 'center', color: balanced ? '#16a34a' : '#dc2626' }}>
          مدين {totalDebit.toFixed(3)} / دائن {totalCredit.toFixed(3)} {balanced ? '✓ متوازن' : '✗ غير متوازن'}
        </span>
      </div>

      <div style={card}>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <input placeholder="رقم القيد (تلقائي)" value={header.entry_number || ''} onChange={(e) => setHeader({ ...header, entry_number: e.target.value })} />
          <select value={header.fiscal_year_id || ''} onChange={(e) => setHeader({ ...header, fiscal_year_id: e.target.value })}>
            {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
          </select>
          <input type="date" value={header.entry_date} onChange={(e) => setHeader({ ...header, entry_date: e.target.value })} />
          <select value={header.branch_id || ''} onChange={(e) => setHeader({ ...header, branch_id: e.target.value })}>
            <option value="">— الفرع —</option>
            {branches.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </select>
          <input style={{ width: 60 }} placeholder="عملة" value={header.currency_code} onChange={(e) => setHeader({ ...header, currency_code: e.target.value })} />
          <input style={{ width: 90 }} type="number" step="0.000001" placeholder="سعر التحويل" value={header.exchange_rate} onChange={(e) => setHeader({ ...header, exchange_rate: e.target.value })} />
          <input style={{ flex: 1, minWidth: 200 }} placeholder="البيان (ينسخ للسطر الأول)" value={header.description} onChange={(e) => onHeaderDesc(e.target.value)} />
        </div>
      </div>

      <table style={{ width: '100%', borderCollapse: 'collapse', background: '#fff', marginTop: 8, fontSize: 13 }}>
        <thead><tr>
          <th style={th}>الحساب (F1 بحث)</th><th style={th}>مدين</th><th style={th}>دائن</th>
          <th style={th}>مركز أساسي</th><th style={th}>مركز إضافي</th><th style={th}>مرجع</th><th style={th}>البيان</th><th style={th}>اسم الخصم</th><th style={th}></th>
        </tr></thead>
        <tbody>
          {lines.map((l, i) => (
            <tr key={i}>
              <td style={td}>
                <input list="acc-list" ref={(el) => (lineRefs.current[i] = el)}
                  onKeyDown={(e) => { if (e.key === 'F1') { e.preventDefault(); lineRefs.current[i]?.focus() } }}
                  value={accDisplay(l.account_id, accounts)}
                  onChange={(e) => setLine(i, { account_id: accIdFromDisplay(e.target.value, accounts) })}
                  placeholder="رقم/اسم الحساب" style={{ width: 150 }} />
              </td>
              <td style={td}><input type="number" step="0.001" value={l.debit} onChange={(e) => setLine(i, { debit: e.target.value, credit: '' })} style={{ width: 80 }} /></td>
              <td style={td}><input type="number" step="0.001" value={l.credit} onChange={(e) => setLine(i, { credit: e.target.value, debit: '' })} style={{ width: 80 }} /></td>
              <td style={td}>
                <select value={l.cost_center_id} onChange={(e) => setLine(i, { cost_center_id: e.target.value })}>
                  <option value="">—</option>
                  {costCenters.map((c) => <option key={c.id} value={c.id}>{c.code} {c.name}</option>)}
                </select>
              </td>
              <td style={td}>
                <select value={l.cost_center_extra_id} onChange={(e) => onExtraChange(i, e.target.value)}>
                  <option value="">—</option>
                  {costCenters.map((c) => <option key={c.id} value={c.id}>{c.code} {c.name}</option>)}
                </select>
              </td>
              <td style={td}><input value={l.reference_number} onChange={(e) => setLine(i, { reference_number: e.target.value })} style={{ width: 70 }} /></td>
              <td style={td}><input value={l.description} onChange={(e) => setLine(i, { description: e.target.value })} style={{ width: 140 }} /></td>
              <td style={td}>{l.counterparty_name}</td>
              <td style={td}><button onClick={() => removeLine(i)} style={{ color: 'crimson', border: 0, background: 'none', cursor: 'pointer' }}>×</button></td>
            </tr>
          ))}
        </tbody>
      </table>
      <datalist id="acc-list">
        {accounts.map((a) => <option key={a.id} value={`${a.code} — ${a.name}`} />)}
      </datalist>
      <button onClick={addLine} style={{ ...btn('#2563eb'), marginTop: 6 }}>+ سطر</button>

      {msg && <p style={{ color: '#16a34a' }}>{msg}</p>}
      {err && <p style={{ color: 'crimson' }}>{err}</p>}

      <h4 style={{ marginTop: 24 }}>القيود الأخيرة</h4>
      <table style={{ width: '100%', borderCollapse: 'collapse', background: '#fff', fontSize: 13 }}>
        <thead><tr><th style={th}>الرقم</th><th style={th}>التاريخ</th><th style={th}>البيان</th><th style={th}>مدين</th><th style={th}>الحالة</th><th style={th}>إجراءات</th></tr></thead>
        <tbody>
          {list.map((e) => (
            <tr key={e.id}>
              <td style={td}>{e.entry_number}</td><td style={td}>{String(e.entry_date).slice(0, 10)}</td>
              <td style={td}>{e.description}</td><td style={td}>{Number(e.total_debit).toFixed(3)}</td>
              <td style={td}>{statusAr(e.status)}</td>
              <td style={td}>
                {e.status === 'DRAFT' && <button onClick={() => action(e.id, 'approve')} style={mini}>اعتماد</button>}
                {e.status === 'APPROVED' && <button onClick={() => action(e.id, 'post')} style={mini}>ترحيل</button>}
                {e.status === 'POSTED' && <button onClick={() => action(e.id, 'reverse')} style={mini}>عكس</button>}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

const accDisplay = (id, accounts) => { const a = accounts.find((x) => String(x.id) === String(id)); return a ? `${a.code} — ${a.name}` : '' }
const accIdFromDisplay = (val, accounts) => { const a = accounts.find((x) => `${x.code} — ${x.name}` === val || String(x.code) === val); return a ? a.id : '' }
const statusAr = (s) => ({ DRAFT: 'مسودة', APPROVED: 'معتمد', POSTED: 'مرحّل', LOCKED: 'مقفل' }[s] || s)
const btn = (bg) => ({ background: bg, color: '#fff', border: 0, borderRadius: 6, padding: '6px 16px', cursor: 'pointer' })
const mini = { background: '#1d4ed8', color: '#fff', border: 0, borderRadius: 4, padding: '3px 8px', cursor: 'pointer', fontSize: 12 }
const card = { background: '#fff', padding: 12, borderRadius: 8 }
const th = { textAlign: 'start', padding: 6, borderBottom: '2px solid #e5e7eb' }
const td = { padding: 4, borderBottom: '1px solid #f0f0f0' }
