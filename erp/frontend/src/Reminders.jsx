import { useEffect, useState } from 'react'
import api from './api.js'

const typeAr = (t) => ({ REORDER: 'إعادة طلب', FISCAL_YEAR: 'سنة مالية', CONTRACT: 'عقد', CHEQUE: 'شيك', CUSTOM: 'مخصّص' }[t] || t)
const statusBadge = (s) => ({ PENDING: 'badge-amber', DONE: 'badge-green', DISMISSED: 'badge-gray' }[s] || 'badge-gray')
const statusAr = (s) => ({ PENDING: 'معلّق', DONE: 'منتهٍ', DISMISSED: 'متجاهَل' }[s] || s)

export default function Reminders() {
  const [list, setList] = useState([])
  const [form, setForm] = useState({ due_date: new Date().toISOString().slice(0, 10) })
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const reload = async () => { setList((await api.get('/reminders')).data.data) }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [])
  const wrap = async (fn) => { setErr(null); setMsg(null); try { await fn(); reload() } catch (e) { setErr(e.response?.data?.message || 'خطأ') } }

  return (
    <div>
      <div className="card">
        <div className="toolbar">
          <button className="btn-primary" onClick={() => wrap(async () => { const r = await api.post('/reminders/generate'); setMsg(r.data.message) })}>🔄 توليد التذكيرات التلقائية</button>
          <span className="muted">(أصناف تحت حد الطلب · سنوات مالية تنتهي قريباً)</span>
        </div>
        <h4>تذكير جديد</h4>
        <form onSubmit={(e) => { e.preventDefault(); wrap(async () => { await api.post('/reminders', form); setForm({ due_date: form.due_date }); setMsg('تم الحفظ') }) }} className="row">
          <input placeholder="العنوان" required value={form.title || ''} onChange={(e) => setForm({ ...form, title: e.target.value })} style={{ minWidth: 240 }} />
          <input type="date" value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} />
          <input placeholder="ملاحظات" value={form.notes || ''} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
          <button className="btn-success" type="submit">+ إضافة</button>
        </form>
        {msg && <p className="ok">{msg}</p>}{err && <p className="err">{err}</p>}
      </div>

      <div className="card">
        <table>
          <thead><tr><th>النوع</th><th>العنوان</th><th>الاستحقاق</th><th>الحالة</th><th>إجراءات</th></tr></thead>
          <tbody>
            {list.map((r) => (
              <tr key={r.id}>
                <td><span className="badge badge-blue">{typeAr(r.type)}</span></td>
                <td>{r.title}</td><td>{String(r.due_date).slice(0, 10)}</td>
                <td><span className={`badge ${statusBadge(r.status)}`}>{statusAr(r.status)}</span></td>
                <td>
                  {r.status === 'PENDING' && <>
                    <button className="btn-success btn-sm" onClick={() => wrap(async () => api.post(`/reminders/${r.id}/done`))}>إنهاء</button>{' '}
                    <button className="btn btn-sm" onClick={() => wrap(async () => api.post(`/reminders/${r.id}/dismiss`))}>تجاهل</button>
                  </>}
                </td>
              </tr>
            ))}
            {list.length === 0 && <tr><td colSpan={5} className="muted">لا توجد تذكيرات</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  )
}
