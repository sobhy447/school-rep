import { useEffect, useState } from 'react'
import api from './api.js'

const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })

export default function Budgets() {
  const [years, setYears] = useState([]); const [fy, setFy] = useState('')
  const [accounts, setAccounts] = useState([]); const [budgets, setBudgets] = useState({})
  const [report, setReport] = useState(null)
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  useEffect(() => {
    Promise.all([api.get('/settings/fiscal-years'), api.get('/accounts')]).then(([y, a]) => {
      setYears(y.data.data); setFy(y.data.data[0]?.id || '')
      // حسابات الإيراد/المصروف الورقية (الأنسب للموازنة)
      setAccounts(a.data.data.filter((x) => x.is_leaf && ['REVENUE', 'EXPENSE'].includes(x.type)))
    }).catch(() => setErr('تعذّر التحميل'))
  }, [])

  const loadReport = async () => {
    if (!fy) return
    const [b, r] = await Promise.all([api.get(`/budgets?fiscal_year_id=${fy}`), api.get(`/reports/budget?fiscal_year_id=${fy}`)])
    const map = {}; b.data.data.forEach((x) => { map[x.account_id] = x.amount })
    setBudgets(map); setReport(r.data.data)
  }
  useEffect(() => { loadReport().catch(() => {}) }, [fy])

  const save = async (accountId, amount) => {
    setErr(null); setMsg(null)
    try { await api.post('/budgets', { fiscal_year_id: fy, account_id: accountId, amount: Number(amount || 0) }); setMsg('تم الحفظ'); loadReport() }
    catch (e) { setErr(e.response?.data?.message || 'خطأ') }
  }

  return (
    <div>
      <div className="card">
        <div className="row">
          <label>السنة المالية:</label>
          <select value={fy} onChange={(e) => setFy(e.target.value)}>{years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}</select>
        </div>
        {msg && <p className="ok">{msg}</p>}{err && <p className="err">{err}</p>}
      </div>

      <div className="card">
        <h4>تحديد الموازنات</h4>
        <table>
          <thead><tr><th>الحساب</th><th>النوع</th><th>المبلغ المقدّر</th><th></th></tr></thead>
          <tbody>
            {accounts.map((a) => (
              <tr key={a.id}>
                <td>{a.code} — {a.name}</td><td>{a.type === 'REVENUE' ? 'إيراد' : 'مصروف'}</td>
                <td><input type="number" step="0.001" defaultValue={budgets[a.id] || ''} onChange={(e) => (budgets[a.id] = e.target.value)} style={{ width: 120 }} /></td>
                <td><button className="btn-success btn-sm" onClick={() => save(a.id, budgets[a.id])}>حفظ</button></td>
              </tr>
            ))}
            {accounts.length === 0 && <tr><td colSpan={4} className="muted">لا توجد حسابات إيراد/مصروف</td></tr>}
          </tbody>
        </table>
      </div>

      {report && (
        <div className="card">
          <h4>الموازنة مقابل الفعلي</h4>
          <table>
            <thead><tr><th>الحساب</th><th>الموازنة</th><th>الفعلي</th><th>الفرق</th><th>% الاستخدام</th></tr></thead>
            <tbody>
              {report.rows.map((r) => (
                <tr key={r.account_id}>
                  <td>{r.name}</td><td>{n(r.budget)}</td><td>{n(r.actual)}</td>
                  <td style={{ color: r.variance >= 0 ? 'var(--success)' : 'var(--danger)' }}>{n(r.variance)}</td>
                  <td><span className={`badge ${r.used_pct > 100 ? 'badge-red' : r.used_pct > 80 ? 'badge-amber' : 'badge-green'}`}>{r.used_pct}%</span></td>
                </tr>
              ))}
              {report.rows.length === 0 && <tr><td colSpan={5} className="muted">لم تُحدَّد موازنات بعد</td></tr>}
            </tbody>
            <tfoot><tr><td>الإجمالي</td><td>{n(report.total_budget)}</td><td>{n(report.total_actual)}</td><td>{n(report.total_variance)}</td><td></td></tr></tfoot>
          </table>
        </div>
      )}
    </div>
  )
}
