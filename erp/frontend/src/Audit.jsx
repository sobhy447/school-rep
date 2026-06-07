import { useEffect, useState } from 'react'
import api from './api.js'

const actionAr = (a) => ({ CREATE: 'إنشاء', UPDATE: 'تعديل', DELETE: 'حذف' }[a] || a)
const actionBadge = (a) => ({ CREATE: 'badge-green', UPDATE: 'badge-amber', DELETE: 'badge-red' }[a] || 'badge-gray')
const typeAr = (t) => ({ Account: 'حساب', JournalEntry: 'قيد/سند', SalesInvoice: 'فاتورة مبيعات', PurchaseInvoice: 'فاتورة مشتريات', Employee: 'موظف', FixedAsset: 'أصل ثابت' }[t] || t)

export default function Audit() {
  const [logs, setLogs] = useState([])
  const [filter, setFilter] = useState({ action: '', type: '' })
  const [err, setErr] = useState(null)

  const load = async () => {
    setErr(null)
    try {
      const q = new URLSearchParams()
      if (filter.action) q.set('action', filter.action)
      if (filter.type) q.set('type', filter.type)
      setLogs((await api.get(`/audit-logs?${q}`)).data.data)
    } catch (e) { setErr(e.response?.data?.message || 'تعذّر التحميل') }
  }
  useEffect(() => { load() }, [filter])

  const fmt = (o) => o ? Object.entries(o).filter(([k]) => !['company_id', 'id'].includes(k)).map(([k, v]) => `${k}: ${v ?? '—'}`).join(' · ') : '—'

  return (
    <div>
      <div className="card">
        <div className="toolbar">
          <select value={filter.action} onChange={(e) => setFilter({ ...filter, action: e.target.value })}>
            <option value="">كل العمليات</option><option value="CREATE">إنشاء</option><option value="UPDATE">تعديل</option><option value="DELETE">حذف</option>
          </select>
          <select value={filter.type} onChange={(e) => setFilter({ ...filter, type: e.target.value })}>
            <option value="">كل الأنواع</option>
            {['Account', 'JournalEntry', 'SalesInvoice', 'PurchaseInvoice', 'Employee', 'FixedAsset'].map((t) => <option key={t} value={t}>{typeAr(t)}</option>)}
          </select>
        </div>
        {err && <p className="err">{err}</p>}
        <table>
          <thead><tr><th>التاريخ</th><th>المستخدم</th><th>العملية</th><th>النوع</th><th>التغييرات</th></tr></thead>
          <tbody>
            {logs.map((l) => (
              <tr key={l.id}>
                <td style={{ whiteSpace: 'nowrap' }}>{new Date(l.created_at).toLocaleString('ar-EG')}</td>
                <td>{l.user_name}</td>
                <td><span className={`badge ${actionBadge(l.action)}`}>{actionAr(l.action)}</span></td>
                <td>{typeAr(l.auditable_type)} #{l.auditable_id}</td>
                <td className="muted" style={{ fontSize: 12 }}>{fmt(l.new_values || l.old_values)}</td>
              </tr>
            ))}
            {logs.length === 0 && <tr><td colSpan={5} className="muted">لا توجد سجلات</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  )
}
