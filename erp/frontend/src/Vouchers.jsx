import { useEffect, useState } from 'react'
import api from './api.js'

// شاشة السندات: قبض (الرئيسي مدين) / صرف (الرئيسي دائن). تُرحَّل تلقائياً.
const emptyLine = () => ({ account_id: '', amount: '', cost_center_id: '', cost_center_extra_id: '' })

export default function Vouchers() {
  const [type, setType] = useState('PAYMENT')
  const [years, setYears] = useState([])
  const [accounts, setAccounts] = useState([])
  const [cashAccounts, setCashAccounts] = useState([])
  const [costCenters, setCostCenters] = useState([])
  const [list, setList] = useState([])
  const [head, setHead] = useState({ entry_date: new Date().toISOString().slice(0, 10), party_name: '', description: '' })
  const [main, setMain] = useState({ account_id: '', amount: '' })
  const [lines, setLines] = useState([emptyLine()])
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const reload = async () => {
    const [y, a, c, v] = await Promise.all([
      api.get('/settings/fiscal-years'), api.get('/accounts'),
      api.get('/settings/cost-centers'), api.get('/vouchers'),
    ])
    setYears(y.data.data); setList(v.data.data)
    const leaves = a.data.data.filter((x) => x.is_leaf)
    setAccounts(leaves); setCashAccounts(leaves.filter((x) => x.is_cash_or_bank))
    setCostCenters(c.data.data)
    if (y.data.data[0]) setHead((h) => ({ ...h, fiscal_year_id: h.fiscal_year_id || y.data.data[0].id }))
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [])

  const mainLabel = type === 'RECEIPT' ? 'المقبوض في (نقدية/بنك) — مدين' : 'المصروف من (نقدية/بنك) — دائن'
  const linesLabel = type === 'RECEIPT' ? 'الأطراف الدائنة' : 'الأطراف المدينة'
  const linesTotal = lines.reduce((s, l) => s + Number(l.amount || 0), 0)
  const ok = Number(main.amount || 0) > 0 && Math.abs(linesTotal - Number(main.amount || 0)) < 0.0005 && main.account_id

  const setLine = (i, patch) => setLines((ls) => ls.map((l, idx) => (idx === i ? { ...l, ...patch } : l)))
  const onExtra = (i, ccId) => {
    const cc = costCenters.find((c) => String(c.id) === String(ccId))
    const patch = { cost_center_extra_id: ccId }
    if (cc && cc.linked_account_id && !lines[i].account_id) patch.account_id = cc.linked_account_id
    setLine(i, patch)
  }

  const save = async () => {
    setErr(null); setMsg(null)
    try {
      const payload = {
        type, ...head,
        main: { account_id: main.account_id, amount: Number(main.amount) },
        lines: lines.filter((l) => l.account_id || l.cost_center_extra_id).map((l) => ({
          account_id: l.account_id || null,
          debit: type === 'PAYMENT' ? Number(l.amount || 0) : 0,
          credit: type === 'RECEIPT' ? Number(l.amount || 0) : 0,
          cost_center_id: l.cost_center_id || null,
          cost_center_extra_id: l.cost_center_extra_id || null,
        })),
      }
      const r = await api.post('/vouchers', payload)
      setMsg(`تم حفظ السند وترحيله: ${r.data.data.entry_number}`)
      setMain({ account_id: '', amount: '' }); setLines([emptyLine()]); setHead((h) => ({ ...h, party_name: '', description: '' }))
      reload()
    } catch (e) {
      setErr(e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'تعذّر الحفظ')
    }
  }

  return (
    <div style={{ maxWidth: 1000, margin: '20px auto', fontFamily: 'system-ui' }}>
      <h3>السندات</h3>
      <div style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
        {[['PAYMENT', 'سند صرف'], ['RECEIPT', 'سند قبض']].map(([v, ar]) => (
          <button key={v} onClick={() => setType(v)} style={btn(type === v ? '#1d4ed8' : '#e5e7eb', type === v ? '#fff' : '#111')}>{ar}</button>
        ))}
      </div>

      <div style={card}>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 8 }}>
          <select value={head.fiscal_year_id || ''} onChange={(e) => setHead({ ...head, fiscal_year_id: e.target.value })}>
            {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
          </select>
          <input type="date" value={head.entry_date} onChange={(e) => setHead({ ...head, entry_date: e.target.value })} />
          <input placeholder={type === 'RECEIPT' ? 'المقبوض منه' : 'المصروف له'} value={head.party_name} onChange={(e) => setHead({ ...head, party_name: e.target.value })} />
          <input style={{ flex: 1, minWidth: 180 }} placeholder="البيان" value={head.description} onChange={(e) => setHead({ ...head, description: e.target.value })} />
        </div>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center', background: '#eef2ff', padding: 8, borderRadius: 6 }}>
          <b style={{ minWidth: 220 }}>{mainLabel}</b>
          <select value={main.account_id} onChange={(e) => setMain({ ...main, account_id: e.target.value })}>
            <option value="">— اختر حساب نقدية/بنك —</option>
            {cashAccounts.map((a) => <option key={a.id} value={a.id}>{a.code} {a.name}</option>)}
          </select>
          <input type="number" step="0.001" placeholder="المبلغ" value={main.amount} onChange={(e) => setMain({ ...main, amount: e.target.value })} />
        </div>
      </div>

      <h4>{linesLabel}</h4>
      <table style={{ width: '100%', borderCollapse: 'collapse', background: '#fff', fontSize: 13 }}>
        <thead><tr><th style={th}>الحساب</th><th style={th}>المبلغ</th><th style={th}>مركز أساسي</th><th style={th}>مركز إضافي</th><th style={th}></th></tr></thead>
        <tbody>
          {lines.map((l, i) => (
            <tr key={i}>
              <td style={td}>
                <select value={l.account_id} onChange={(e) => setLine(i, { account_id: e.target.value })}>
                  <option value="">— حساب —</option>
                  {accounts.map((a) => <option key={a.id} value={a.id}>{a.code} {a.name}</option>)}
                </select>
              </td>
              <td style={td}><input type="number" step="0.001" value={l.amount} onChange={(e) => setLine(i, { amount: e.target.value })} style={{ width: 90 }} /></td>
              <td style={td}>
                <select value={l.cost_center_id} onChange={(e) => setLine(i, { cost_center_id: e.target.value })}>
                  <option value="">—</option>
                  {costCenters.map((c) => <option key={c.id} value={c.id}>{c.code} {c.name}</option>)}
                </select>
              </td>
              <td style={td}>
                <select value={l.cost_center_extra_id} onChange={(e) => onExtra(i, e.target.value)}>
                  <option value="">—</option>
                  {costCenters.map((c) => <option key={c.id} value={c.id}>{c.code} {c.name}</option>)}
                </select>
              </td>
              <td style={td}><button onClick={() => setLines((ls) => ls.length > 1 ? ls.filter((_, x) => x !== i) : ls)} style={{ color: 'crimson', border: 0, background: 'none', cursor: 'pointer' }}>×</button></td>
            </tr>
          ))}
        </tbody>
      </table>
      <div style={{ marginTop: 6, display: 'flex', gap: 8, alignItems: 'center' }}>
        <button onClick={() => setLines((ls) => [...ls, emptyLine()])} style={btn('#2563eb', '#fff')}>+ سطر</button>
        <button onClick={save} disabled={!ok} style={btn(ok ? '#16a34a' : '#9ca3af', '#fff')}>حفظ وترحيل</button>
        <span style={{ color: ok ? '#16a34a' : '#dc2626' }}>الأطراف {linesTotal.toFixed(3)} / الرئيسي {Number(main.amount || 0).toFixed(3)}</span>
      </div>
      {msg && <p style={{ color: '#16a34a' }}>{msg}</p>}
      {err && <p style={{ color: 'crimson' }}>{err}</p>}

      <h4 style={{ marginTop: 24 }}>السندات الأخيرة</h4>
      <table style={{ width: '100%', borderCollapse: 'collapse', background: '#fff', fontSize: 13 }}>
        <thead><tr><th style={th}>الرقم</th><th style={th}>النوع</th><th style={th}>التاريخ</th><th style={th}>الطرف</th><th style={th}>المبلغ</th><th style={th}>الحالة</th></tr></thead>
        <tbody>
          {list.map((v) => (
            <tr key={v.id}>
              <td style={td}>{v.entry_number}</td><td style={td}>{typeAr(v.type)}</td>
              <td style={td}>{String(v.entry_date).slice(0, 10)}</td><td style={td}>{v.party_name}</td>
              <td style={td}>{Number(v.total_debit).toFixed(3)}</td><td style={td}>مرحّل</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

const typeAr = (t) => ({ RECEIPT: 'قبض', PAYMENT: 'صرف', TRANSFER: 'تحويل' }[t] || t)
const btn = (bg, color = '#fff') => ({ background: bg, color, border: 0, borderRadius: 6, padding: '6px 16px', cursor: 'pointer' })
const card = { background: '#fff', padding: 12, borderRadius: 8 }
const th = { textAlign: 'start', padding: 6, borderBottom: '2px solid #e5e7eb' }
const td = { padding: 4, borderBottom: '1px solid #f0f0f0' }
