import { useEffect, useState } from 'react'
import api from './api.js'

const TYPES = [
  { v: 'ASSET', ar: 'أصول' },
  { v: 'LIABILITY', ar: 'خصوم' },
  { v: 'EQUITY', ar: 'حقوق ملكية' },
  { v: 'REVENUE', ar: 'إيرادات' },
  { v: 'EXPENSE', ar: 'مصروفات' },
]

// عرض عقدة الشجرة بشكل متداخل
function Node({ node, depth }) {
  return (
    <>
      <tr>
        <td style={{ ...td, paddingInlineStart: 8 + depth * 22 }}>
          <span style={{ color: node.is_leaf ? '#111' : '#2563eb', fontWeight: node.is_leaf ? 400 : 600 }}>
            {node.code} — {node.name}
          </span>
        </td>
        <td style={td}>{TYPES.find((t) => t.v === node.type)?.ar}</td>
        <td style={td}>{node.normal_balance === 'DEBIT' ? 'مدين' : 'دائن'}</td>
        <td style={td}>{node.statement === 'BALANCE_SHEET' ? 'الميزانية' : 'قائمة الدخل'}</td>
        <td style={{ ...td, textAlign: 'end' }}>{Number(node.balance).toFixed(3)}</td>
      </tr>
      {node.children?.map((c) => <Node key={c.id} node={c} depth={depth + 1} />)}
    </>
  )
}

export default function Accounts() {
  const [tree, setTree] = useState([])
  const [flat, setFlat] = useState([])
  const [form, setForm] = useState({ type: 'ASSET', opening_balance_type: 'DEBIT' })
  const [error, setError] = useState(null)

  const load = async () => {
    setError(null)
    try {
      const [t, f] = await Promise.all([api.get('/accounts/tree'), api.get('/accounts')])
      setTree(t.data.data)
      setFlat(f.data.data)
    } catch (e) {
      setError(e.response?.data?.message || 'تعذّر التحميل')
    }
  }

  useEffect(() => { load() }, [])

  const save = async (e) => {
    e.preventDefault()
    setError(null)
    try {
      await api.post('/accounts', {
        ...form,
        parent_id: form.parent_id || null,
        opening_balance: Number(form.opening_balance || 0),
      })
      setForm({ type: 'ASSET', opening_balance_type: 'DEBIT' })
      load()
    } catch (e) {
      setError(e.response?.data?.message || 'تعذّر الحفظ')
    }
  }

  return (
    <div style={{ maxWidth: 900, margin: '20px auto', fontFamily: 'system-ui' }}>
      <h3>دليل الحسابات</h3>
      <form onSubmit={save} style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 16, background: '#fff', padding: 12, borderRadius: 8 }}>
        <input placeholder="الرمز" required value={form.code || ''} onChange={(e) => setForm({ ...form, code: e.target.value })} />
        <input placeholder="الاسم" required value={form.name || ''} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
          {TYPES.map((t) => <option key={t.v} value={t.v}>{t.ar}</option>)}
        </select>
        <select value={form.parent_id || ''} onChange={(e) => setForm({ ...form, parent_id: e.target.value })}>
          <option value="">— بدون أب —</option>
          {flat.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
        </select>
        <input type="number" step="0.001" placeholder="رصيد افتتاحي" value={form.opening_balance || ''} onChange={(e) => setForm({ ...form, opening_balance: e.target.value })} />
        <select value={form.opening_balance_type} onChange={(e) => setForm({ ...form, opening_balance_type: e.target.value })}>
          <option value="DEBIT">مدين</option>
          <option value="CREDIT">دائن</option>
        </select>
        <button type="submit" style={{ background: '#16a34a', color: '#fff', border: 0, borderRadius: 6, padding: '6px 16px' }}>إضافة حساب</button>
      </form>

      {error && <p style={{ color: 'crimson' }}>{error}</p>}

      <table style={{ width: '100%', borderCollapse: 'collapse', background: '#fff' }}>
        <thead>
          <tr>
            <th style={th}>الحساب</th><th style={th}>النوع</th><th style={th}>الطبيعة</th>
            <th style={th}>يظهر في</th><th style={{ ...th, textAlign: 'end' }}>الرصيد</th>
          </tr>
        </thead>
        <tbody>
          {tree.map((n) => <Node key={n.id} node={n} depth={0} />)}
          {tree.length === 0 && <tr><td style={td} colSpan={5}>لا توجد حسابات</td></tr>}
        </tbody>
      </table>
    </div>
  )
}

const th = { textAlign: 'start', padding: 8, borderBottom: '2px solid #e5e7eb', fontSize: 14 }
const td = { padding: 8, borderBottom: '1px solid #f0f0f0', fontSize: 14 }
