import { useEffect, useState } from 'react'
import api from './api.js'

// تعريف كيانات الإعدادات: المسار + الحقول المعروضة في الجدول والنموذج.
const ENTITIES = {
  branches: {
    ar: 'الفروع', en: 'Branches',
    fields: [
      { key: 'code', label: 'الرمز', required: true },
      { key: 'name', label: 'الاسم', required: true },
      { key: 'name_en', label: 'الاسم (EN)' },
      { key: 'phone', label: 'الهاتف' },
    ],
  },
  'fiscal-years': {
    ar: 'السنوات المالية', en: 'Fiscal Years',
    fields: [
      { key: 'name', label: 'الاسم', required: true },
      { key: 'start_date', label: 'من', type: 'date', required: true },
      { key: 'end_date', label: 'إلى', type: 'date', required: true },
    ],
  },
  currencies: {
    ar: 'العملات', en: 'Currencies',
    fields: [
      { key: 'code', label: 'الرمز', required: true },
      { key: 'name', label: 'الاسم', required: true },
      { key: 'exchange_rate', label: 'سعر الصرف', type: 'number' },
    ],
  },
  'cost-centers': {
    ar: 'مراكز التكلفة', en: 'Cost Centers',
    fields: [
      { key: 'code', label: 'الرمز', required: true },
      { key: 'name', label: 'الاسم', required: true },
    ],
  },
  'voucher-types': {
    ar: 'أنواع السندات', en: 'Voucher Types',
    fields: [
      { key: 'code', label: 'الرمز', required: true },
      { key: 'name', label: 'الاسم', required: true },
      { key: 'direction', label: 'الاتجاه', type: 'select',
        options: ['RECEIPT', 'PAYMENT', 'TRANSFER'], required: true },
    ],
  },
  'tax-rates': {
    ar: 'الضرائب', en: 'Tax Rates',
    fields: [
      { key: 'code', label: 'الرمز', required: true },
      { key: 'name', label: 'الاسم', required: true },
      { key: 'rate', label: 'النسبة %', type: 'number', required: true },
    ],
  },
}

export default function Settings() {
  const [tab, setTab] = useState('branches')
  const [rows, setRows] = useState([])
  const [form, setForm] = useState({})
  const [error, setError] = useState(null)
  const def = ENTITIES[tab]

  const load = async () => {
    setError(null)
    try {
      const r = await api.get(`/settings/${tab}`)
      setRows(r.data.data)
    } catch (e) {
      setError(e.response?.data?.message || 'تعذّر التحميل')
    }
  }

  useEffect(() => { setForm({}); load() }, [tab])

  const save = async (e) => {
    e.preventDefault()
    setError(null)
    try {
      await api.post(`/settings/${tab}`, form)
      setForm({})
      load()
    } catch (e) {
      setError(e.response?.data?.message || 'تعذّر الحفظ')
    }
  }

  const remove = async (id) => {
    try { await api.delete(`/settings/${tab}/${id}`); load() } catch {}
  }

  return (
    <div style={{ maxWidth: 900, margin: '20px auto', fontFamily: 'system-ui' }}>
      <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 16 }}>
        {Object.entries(ENTITIES).map(([key, e]) => (
          <button key={key} onClick={() => setTab(key)}
            style={{ padding: '6px 12px', background: tab === key ? '#2563eb' : '#e5e7eb',
                     color: tab === key ? '#fff' : '#111', border: 0, borderRadius: 6, cursor: 'pointer' }}>
            {e.ar}
          </button>
        ))}
      </div>

      <form onSubmit={save} style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 16,
                                     background: '#fff', padding: 12, borderRadius: 8 }}>
        {def.fields.map((f) => (
          f.type === 'select' ? (
            <select key={f.key} required={f.required} value={form[f.key] || ''}
              onChange={(ev) => setForm({ ...form, [f.key]: ev.target.value })}>
              <option value="">{f.label}</option>
              {f.options.map((o) => <option key={o} value={o}>{o}</option>)}
            </select>
          ) : (
            <input key={f.key} type={f.type || 'text'} placeholder={f.label} required={f.required}
              value={form[f.key] || ''} onChange={(ev) => setForm({ ...form, [f.key]: ev.target.value })} />
          )
        ))}
        <button type="submit" style={{ background: '#16a34a', color: '#fff', border: 0, borderRadius: 6, padding: '6px 16px' }}>
          إضافة
        </button>
      </form>

      {error && <p style={{ color: 'crimson' }}>{error}</p>}

      <table style={{ width: '100%', borderCollapse: 'collapse', background: '#fff' }}>
        <thead>
          <tr>
            {def.fields.map((f) => <th key={f.key} style={th}>{f.label}</th>)}
            <th style={th}></th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.id}>
              {def.fields.map((f) => <td key={f.key} style={td}>{String(row[f.key] ?? '')}</td>)}
              <td style={td}>
                <button onClick={() => remove(row.id)} style={{ color: 'crimson', border: 0, background: 'none', cursor: 'pointer' }}>حذف</button>
              </td>
            </tr>
          ))}
          {rows.length === 0 && <tr><td style={td} colSpan={def.fields.length + 1}>لا توجد بيانات</td></tr>}
        </tbody>
      </table>
    </div>
  )
}

const th = { textAlign: 'start', padding: 8, borderBottom: '2px solid #e5e7eb', fontSize: 14 }
const td = { padding: 8, borderBottom: '1px solid #f0f0f0', fontSize: 14 }
