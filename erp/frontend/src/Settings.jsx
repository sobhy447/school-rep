import { useEffect, useState } from 'react'
import api from './api.js'

const ENTITIES = {
  branches: { ar: 'الفروع', fields: [
    { key: 'code', label: 'الرمز', required: true }, { key: 'name', label: 'الاسم', required: true },
    { key: 'name_en', label: 'الاسم (EN)' }, { key: 'phone', label: 'الهاتف' }] },
  'fiscal-years': { ar: 'السنوات المالية', fields: [
    { key: 'name', label: 'الاسم', required: true }, { key: 'start_date', label: 'من', type: 'date', required: true },
    { key: 'end_date', label: 'إلى', type: 'date', required: true }] },
  currencies: { ar: 'العملات', fields: [
    { key: 'code', label: 'الرمز', required: true }, { key: 'name', label: 'الاسم', required: true },
    { key: 'exchange_rate', label: 'سعر الصرف', type: 'number' }] },
  'cost-centers': { ar: 'مراكز التكلفة', fields: [
    { key: 'code', label: 'الرمز', required: true }, { key: 'name', label: 'الاسم', required: true }] },
  'voucher-types': { ar: 'أنواع السندات', fields: [
    { key: 'code', label: 'الرمز', required: true }, { key: 'name', label: 'الاسم', required: true },
    { key: 'direction', label: 'الاتجاه', type: 'select', options: ['RECEIPT', 'PAYMENT', 'TRANSFER'], required: true }] },
  'tax-rates': { ar: 'الضرائب', fields: [
    { key: 'code', label: 'الرمز', required: true }, { key: 'name', label: 'الاسم', required: true },
    { key: 'rate', label: 'النسبة %', type: 'number', required: true }] },
}

export default function Settings() {
  const [tab, setTab] = useState('branches')
  const [rows, setRows] = useState([])
  const [form, setForm] = useState({})
  const [error, setError] = useState(null)
  const def = ENTITIES[tab]

  const load = async () => {
    setError(null)
    try { const r = await api.get(`/settings/${tab}`); setRows(r.data.data) }
    catch (e) { setError(e.response?.data?.message || 'تعذّر التحميل') }
  }
  useEffect(() => { setForm({}); load() }, [tab])

  const save = async (e) => {
    e.preventDefault(); setError(null)
    try { await api.post(`/settings/${tab}`, form); setForm({}); load() }
    catch (e) { setError(e.response?.data?.message || 'تعذّر الحفظ') }
  }
  const remove = async (id) => { try { await api.delete(`/settings/${tab}/${id}`); load() } catch {} }

  return (
    <div>
      <div className="toolbar">
        {Object.entries(ENTITIES).map(([key, e]) => (
          <button key={key} className={`tab ${tab === key ? 'active' : ''}`} onClick={() => setTab(key)}>{e.ar}</button>
        ))}
      </div>

      <div className="card">
        <h4>إضافة {def.ar}</h4>
        <form onSubmit={save} className="row">
          {def.fields.map((f) => (
            f.type === 'select'
              ? <select key={f.key} required={f.required} value={form[f.key] || ''} onChange={(ev) => setForm({ ...form, [f.key]: ev.target.value })}>
                  <option value="">{f.label}</option>{f.options.map((o) => <option key={o} value={o}>{o}</option>)}
                </select>
              : <input key={f.key} type={f.type || 'text'} placeholder={f.label} required={f.required} value={form[f.key] || ''} onChange={(ev) => setForm({ ...form, [f.key]: ev.target.value })} />
          ))}
          <button className="btn-success" type="submit">+ إضافة</button>
        </form>
        {error && <p className="err">{error}</p>}
      </div>

      <div className="card">
        <table>
          <thead><tr>{def.fields.map((f) => <th key={f.key}>{f.label}</th>)}<th></th></tr></thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id}>
                {def.fields.map((f) => <td key={f.key}>{String(row[f.key] ?? '')}</td>)}
                <td><button className="btn-danger btn-sm" onClick={() => remove(row.id)}>حذف</button></td>
              </tr>
            ))}
            {rows.length === 0 && <tr><td colSpan={def.fields.length + 1} className="muted">لا توجد بيانات</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  )
}
