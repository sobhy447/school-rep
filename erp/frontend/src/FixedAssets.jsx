import { useEffect, useState } from 'react'
import api from './api.js'
import { SkeletonTable } from './Skeleton.jsx'

const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const today = () => new Date().toISOString().slice(0, 10)

export default function FixedAssets() {
  const [assets, setAssets] = useState([]); const [accounts, setAccounts] = useState([]); const [years, setYears] = useState([])
  const [form, setForm] = useState({ method: 'STRAIGHT_LINE', acquisition_date: today(), salvage_value: 0 })
  const [period, setPeriod] = useState(today())
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)
  const [loading, setLoading] = useState(true)

  const reload = async () => {
    const [a, ac, y] = await Promise.all([api.get('/fixed-assets'), api.get('/accounts'), api.get('/settings/fiscal-years')])
    setAssets(a.data.data); setAccounts(ac.data.data.filter((x) => x.is_leaf)); setYears(y.data.data)
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')).finally(() => setLoading(false)) }, [])
  const fyId = () => years[0]?.id

  const save = async (e) => {
    e.preventDefault(); setErr(null); setMsg(null)
    try {
      await api.post('/fixed-assets', { ...form, cost: Number(form.cost), salvage_value: Number(form.salvage_value || 0), useful_life_months: Number(form.useful_life_months), declining_rate: form.declining_rate ? Number(form.declining_rate) : null })
      setForm({ method: 'STRAIGHT_LINE', acquisition_date: today(), salvage_value: 0 }); setMsg('تم حفظ الأصل'); reload()
    } catch (e) { setErr(e.response?.data?.message || 'تعذّر الحفظ') }
  }
  const depreciateAll = async () => {
    setErr(null); setMsg(null)
    try { const r = await api.post('/fixed-assets/depreciate-all', { period_date: period, fiscal_year_id: fyId() }); setMsg(r.data.message); reload() }
    catch (e) { setErr(e.response?.data?.message) }
  }
  const depreciate = async (id) => { try { await api.post(`/fixed-assets/${id}/depreciate`, { period_date: period, fiscal_year_id: fyId() }); reload() } catch (e) { setErr(e.response?.data?.message) } }

  const accSel = (key, label) => (
    <select required value={form[key] || ''} onChange={(e) => setForm({ ...form, [key]: e.target.value })}>
      <option value="">{label}</option>{accounts.map((a) => <option key={a.id} value={a.id}>{a.code} {a.name}</option>)}
    </select>
  )

  return (
    <div>
      <div className="card">
        <h4>إضافة أصل ثابت</h4>
        <form onSubmit={save} className="row">
          <input placeholder="الرمز" required value={form.code || ''} onChange={(e) => setForm({ ...form, code: e.target.value })} />
          <input placeholder="الاسم" required value={form.name || ''} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          {accSel('asset_account_id', 'حساب الأصل')}
          {accSel('accumulated_depreciation_account_id', 'مجمع الإهلاك')}
          {accSel('depreciation_expense_account_id', 'مصروف الإهلاك')}
          <input type="date" value={form.acquisition_date} onChange={(e) => setForm({ ...form, acquisition_date: e.target.value })} />
          <input type="number" step="0.001" placeholder="التكلفة" required value={form.cost || ''} onChange={(e) => setForm({ ...form, cost: e.target.value })} />
          <input type="number" step="0.001" placeholder="القيمة التخريدية" value={form.salvage_value} onChange={(e) => setForm({ ...form, salvage_value: e.target.value })} />
          <input type="number" placeholder="العمر (شهور)" required value={form.useful_life_months || ''} onChange={(e) => setForm({ ...form, useful_life_months: e.target.value })} />
          <select value={form.method} onChange={(e) => setForm({ ...form, method: e.target.value })}>
            <option value="STRAIGHT_LINE">قسط ثابت</option><option value="DECLINING_BALANCE">متناقص</option>
          </select>
          {form.method === 'DECLINING_BALANCE' && <input type="number" step="0.01" placeholder="نسبة سنوية %" value={form.declining_rate || ''} onChange={(e) => setForm({ ...form, declining_rate: e.target.value })} />}
          <button className="btn-success" type="submit">+ إضافة</button>
        </form>
        {msg && <p className="ok">{msg}</p>}
        {err && <p className="err">{err}</p>}
      </div>

      <div className="card">
        <div className="toolbar">
          <label>تشغيل الإهلاك لفترة:</label>
          <input type="date" value={period} onChange={(e) => setPeriod(e.target.value)} />
          <button className="btn-primary" onClick={depreciateAll}>تشغيل إهلاك كل الأصول</button>
        </div>
        {loading ? <SkeletonTable cols={8} rows={5} /> : (
        <table>
          <thead><tr><th>الرمز</th><th>الاسم</th><th>التكلفة</th><th>مجمع الإهلاك</th><th>القيمة الدفترية</th><th>الطريقة</th><th>الحالة</th><th>إجراءات</th></tr></thead>
          <tbody>
            {assets.map((a) => (
              <tr key={a.id}>
                <td>{a.code}</td><td>{a.name}</td><td>{n(a.cost)}</td><td>{n(a.accumulated_depreciation)}</td><td>{n(a.book_value)}</td>
                <td>{a.method === 'STRAIGHT_LINE' ? 'قسط ثابت' : 'متناقص'}</td>
                <td><span className={`badge ${a.status === 'ACTIVE' ? 'badge-green' : 'badge-gray'}`}>{a.status === 'ACTIVE' ? 'نشط' : 'مستبعد'}</span></td>
                <td>{a.status === 'ACTIVE' && <button className="btn-primary btn-sm" onClick={() => depreciate(a.id)}>إهلاك الفترة</button>}</td>
              </tr>
            ))}
            {assets.length === 0 && <tr><td colSpan={8} className="muted">لا توجد أصول</td></tr>}
          </tbody>
        </table>
        )}
      </div>
    </div>
  )
}
