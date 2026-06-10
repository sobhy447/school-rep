import { useEffect, useState } from 'react'
import api from './api.js'

const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })
const today = () => new Date().toISOString().slice(0, 10)

export default function Hr() {
  const [tab, setTab] = useState('employees')
  const [employees, setEmployees] = useState([]); const [components, setComponents] = useState([]); const [runs, setRuns] = useState([]); const [years, setYears] = useState([])
  const [emp, setEmp] = useState({}); const [comp, setComp] = useState({ type: 'EARNING' })
  const [gen, setGen] = useState({ period_year: 2026, period_month: 1, run_date: today() })
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const reload = async () => {
    const [e, c, r, y] = await Promise.all([api.get('/employees'), api.get('/salary-components'), api.get('/payroll'), api.get('/settings/fiscal-years')])
    setEmployees(e.data.data); setComponents(c.data.data); setRuns(r.data.data); setYears(y.data.data)
    setGen((g) => ({ ...g, fiscal_year_id: g.fiscal_year_id || y.data.data[0]?.id }))
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [])
  const wrap = async (fn) => { setErr(null); setMsg(null); try { await fn(); reload() } catch (e) { setErr(e.response?.data?.message || 'خطأ') } }

  return (
    <div>
      <div className="toolbar">
        {[['employees', 'الموظفون'], ['components', 'مكوّنات الراتب'], ['payroll', 'مسير الرواتب']].map(([k, l]) => (
          <button key={k} className={`tab ${tab === k ? 'active' : ''}`} onClick={() => setTab(k)}>{l}</button>
        ))}
      </div>
      {msg && <p className="ok">{msg}</p>}{err && <p className="err">{err}</p>}

      {tab === 'employees' && (
        <>
          <div className="card"><h4>إضافة موظف</h4>
            <form onSubmit={(e) => { e.preventDefault(); wrap(async () => { await api.post('/employees', { ...emp, basic_salary: Number(emp.basic_salary || 0) }); setEmp({}); setMsg('تم الحفظ') }) }} className="row">
              <input placeholder="الرمز" required value={emp.code || ''} onChange={(e) => setEmp({ ...emp, code: e.target.value })} />
              <input placeholder="الاسم" required value={emp.name || ''} onChange={(e) => setEmp({ ...emp, name: e.target.value })} />
              <input placeholder="القسم" value={emp.department || ''} onChange={(e) => setEmp({ ...emp, department: e.target.value })} />
              <input type="number" step="0.001" placeholder="الراتب الأساسي" value={emp.basic_salary || ''} onChange={(e) => setEmp({ ...emp, basic_salary: e.target.value })} />
              <button className="btn-success" type="submit">+ إضافة</button>
            </form>
          </div>
          <div className="card"><table>
            <thead><tr><th>الرمز</th><th>الاسم</th><th>القسم</th><th>الراتب الأساسي</th></tr></thead>
            <tbody>{employees.map((e) => <tr key={e.id}><td>{e.code}</td><td>{e.name}</td><td>{e.department}</td><td>{n(e.basic_salary)}</td></tr>)}
              {employees.length === 0 && <tr><td colSpan={4} className="muted">لا يوجد موظفون</td></tr>}</tbody>
          </table></div>
        </>
      )}

      {tab === 'components' && (
        <>
          <div className="card"><h4>إضافة مكوّن راتب</h4>
            <form onSubmit={(e) => { e.preventDefault(); wrap(async () => { await api.post('/salary-components', { ...comp, default_amount: Number(comp.default_amount || 0) }); setComp({ type: 'EARNING' }); setMsg('تم الحفظ') }) }} className="row">
              <input placeholder="الرمز" required value={comp.code || ''} onChange={(e) => setComp({ ...comp, code: e.target.value })} />
              <input placeholder="الاسم" required value={comp.name || ''} onChange={(e) => setComp({ ...comp, name: e.target.value })} />
              <select value={comp.type} onChange={(e) => setComp({ ...comp, type: e.target.value })}><option value="EARNING">بدل/استحقاق</option><option value="DEDUCTION">استقطاع</option></select>
              <input type="number" step="0.001" placeholder="القيمة الافتراضية" value={comp.default_amount || ''} onChange={(e) => setComp({ ...comp, default_amount: e.target.value })} />
              <button className="btn-success" type="submit">+ إضافة</button>
            </form>
          </div>
          <div className="card"><table>
            <thead><tr><th>الرمز</th><th>الاسم</th><th>النوع</th><th>القيمة</th></tr></thead>
            <tbody>{components.map((c) => <tr key={c.id}><td>{c.code}</td><td>{c.name}</td><td><span className={`badge ${c.type === 'EARNING' ? 'badge-green' : 'badge-red'}`}>{c.type === 'EARNING' ? 'استحقاق' : 'استقطاع'}</span></td><td>{n(c.default_amount)}</td></tr>)}
              {components.length === 0 && <tr><td colSpan={4} className="muted">لا توجد مكوّنات</td></tr>}</tbody>
          </table></div>
        </>
      )}

      {tab === 'payroll' && (
        <>
          <div className="card"><h4>توليد مسير رواتب</h4>
            <form onSubmit={(e) => { e.preventDefault(); wrap(async () => { await api.post('/payroll/generate', { ...gen, period_year: Number(gen.period_year), period_month: Number(gen.period_month) }); setMsg('تم التوليد') }) }} className="row">
              <select value={gen.fiscal_year_id || ''} onChange={(e) => setGen({ ...gen, fiscal_year_id: e.target.value })}>{years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}</select>
              <input type="number" placeholder="السنة" value={gen.period_year} onChange={(e) => setGen({ ...gen, period_year: e.target.value })} style={{ width: 90 }} />
              <input type="number" min="1" max="12" placeholder="الشهر" value={gen.period_month} onChange={(e) => setGen({ ...gen, period_month: e.target.value })} style={{ width: 70 }} />
              <input type="date" value={gen.run_date} onChange={(e) => setGen({ ...gen, run_date: e.target.value })} />
              <button className="btn-success" type="submit">توليد المسير</button>
            </form>
          </div>
          <div className="card"><table>
            <thead><tr><th>الفترة</th><th>الإجمالي</th><th>الاستقطاعات</th><th>الصافي</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody>
              {runs.map((r) => (
                <tr key={r.id}>
                  <td>{r.period_month}/{r.period_year}</td><td>{n(r.total_earnings)}</td><td>{n(r.total_deductions)}</td><td>{n(r.net_total)}</td>
                  <td><span className={`badge ${r.status === 'POSTED' ? 'badge-green' : 'badge-gray'}`}>{r.status === 'POSTED' ? 'مرحّل' : 'مسودة'}</span></td>
                  <td>{r.status === 'DRAFT' && <button className="btn-primary btn-sm" onClick={() => wrap(async () => { await api.post(`/payroll/${r.id}/post`); setMsg('تم الترحيل') })}>ترحيل</button>}</td>
                </tr>
              ))}
              {runs.length === 0 && <tr><td colSpan={6} className="muted">لا توجد مسيّرات</td></tr>}
            </tbody>
          </table></div>
        </>
      )}
    </div>
  )
}
