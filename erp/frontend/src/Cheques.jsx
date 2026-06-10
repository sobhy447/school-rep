import { useEffect, useState } from 'react'
import api from './api.js'
import { SkeletonTable } from './Skeleton.jsx'

const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const today = () => new Date().toISOString().slice(0, 10)
const statusBadge = (s) => ({ PENDING: 'badge-amber', CLEARED: 'badge-green', BOUNCED: 'badge-red', CANCELLED: 'badge-gray' }[s] || 'badge-gray')
const statusAr = (s) => ({ PENDING: 'منتظر', CLEARED: 'محصّل/مصروف', BOUNCED: 'مرتد', CANCELLED: 'ملغى' }[s] || s)

export default function Cheques() {
  const [list, setList] = useState([]); const [parties, setParties] = useState([]); const [banks, setBanks] = useState([]); const [years, setYears] = useState([])
  const [form, setForm] = useState({ type: 'INCOMING', issue_date: today(), due_date: today() })
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)
  const [loading, setLoading] = useState(true)

  const reload = async () => {
    const [c, p, a, y] = await Promise.all([api.get('/cheques'), api.get('/accounts/parties'), api.get('/accounts'), api.get('/settings/fiscal-years')])
    setList(c.data.data); setParties(p.data.data); setBanks(a.data.data.filter((x) => x.is_leaf && x.is_cash_or_bank)); setYears(y.data.data)
    setForm((f) => ({ ...f, fiscal_year_id: f.fiscal_year_id || y.data.data[0]?.id }))
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')).finally(() => setLoading(false)) }, [])
  const wrap = async (fn) => { setErr(null); setMsg(null); try { await fn(); reload() } catch (e) { setErr(e.response?.data?.message || 'خطأ') } }

  return (
    <div>
      <div className="card">
        <h4>تسجيل شيك</h4>
        <form onSubmit={(e) => { e.preventDefault(); wrap(async () => { await api.post('/cheques', { ...form, amount: Number(form.amount) }); setForm({ type: form.type, issue_date: today(), due_date: today(), fiscal_year_id: form.fiscal_year_id }); setMsg('تم التسجيل') }) }} className="row">
          <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}><option value="INCOMING">وارد (تحصيل)</option><option value="OUTGOING">صادر (صرف)</option></select>
          <input placeholder="رقم الشيك" required value={form.cheque_number || ''} onChange={(e) => setForm({ ...form, cheque_number: e.target.value })} />
          <input placeholder="البنك" value={form.bank_name || ''} onChange={(e) => setForm({ ...form, bank_name: e.target.value })} />
          <input type="number" step="0.001" placeholder="المبلغ" required value={form.amount || ''} onChange={(e) => setForm({ ...form, amount: e.target.value })} />
          <select required value={form.party_account_id || ''} onChange={(e) => setForm({ ...form, party_account_id: e.target.value })}>
            <option value="">— الطرف —</option>{parties.map((p) => <option key={p.id} value={p.id}>{p.code} {p.name}</option>)}
          </select>
          <select required value={form.bank_account_id || ''} onChange={(e) => setForm({ ...form, bank_account_id: e.target.value })}>
            <option value="">— حساب البنك —</option>{banks.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </select>
          <label>الاستحقاق</label><input type="date" value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} />
          <button className="btn-success" type="submit">+ تسجيل</button>
        </form>
        {msg && <p className="ok">{msg}</p>}{err && <p className="err">{err}</p>}
      </div>

      <div className="card">
        {loading ? <SkeletonTable cols={6} rows={5} /> : (
        <table>
          <thead><tr><th>النوع</th><th>الرقم</th><th>المبلغ</th><th>الاستحقاق</th><th>الحالة</th><th>إجراءات</th></tr></thead>
          <tbody>
            {list.map((c) => (
              <tr key={c.id}>
                <td>{c.type === 'INCOMING' ? 'وارد' : 'صادر'}</td><td>{c.cheque_number}</td><td>{n(c.amount)}</td><td>{String(c.due_date).slice(0, 10)}</td>
                <td><span className={`badge ${statusBadge(c.status)}`}>{statusAr(c.status)}</span></td>
                <td>{c.status === 'PENDING' && <>
                  <button className="btn-success btn-sm" onClick={() => wrap(async () => api.post(`/cheques/${c.id}/clear`, {}))}>تحصيل/صرف</button>{' '}
                  <button className="btn-danger btn-sm" onClick={() => wrap(async () => api.post(`/cheques/${c.id}/bounce`))}>ارتداد</button>
                </>}</td>
              </tr>
            ))}
            {list.length === 0 && <tr><td colSpan={6} className="muted">لا توجد شيكات</td></tr>}
          </tbody>
        </table>
        )}
      </div>
    </div>
  )
}
