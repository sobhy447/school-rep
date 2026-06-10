import { useEffect, useState } from 'react'
import api, { openPdf } from './api.js'
import { SkeletonTable } from './Skeleton.jsx'

const TYPES = [
  { v: 'ASSET', ar: 'أصول' }, { v: 'LIABILITY', ar: 'خصوم' }, { v: 'EQUITY', ar: 'حقوق ملكية' },
  { v: 'REVENUE', ar: 'إيرادات' }, { v: 'EXPENSE', ar: 'مصروفات' },
]
const typeAr = (t) => TYPES.find((x) => x.v === t)?.ar
const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })

function Node({ node, depth }) {
  return (
    <>
      <tr>
        <td style={{ paddingInlineStart: 12 + depth * 22 }}>
          <span style={{ fontWeight: node.is_leaf ? 400 : 700, color: node.is_leaf ? 'var(--text)' : 'var(--primary)' }}>
            {!node.is_leaf && '📁 '}{node.is_leaf && '📄 '}{node.code} — {node.name}
          </span>
        </td>
        <td><span className="badge badge-gray">{typeAr(node.type)}</span></td>
        <td>{node.normal_balance === 'DEBIT' ? 'مدين' : 'دائن'}</td>
        <td>{node.statement === 'BALANCE_SHEET' ? 'الميزانية' : 'قائمة الدخل'}</td>
        <td style={{ textAlign: 'end', fontWeight: 600 }}>{n(node.balance)}</td>
        <td>{node.is_leaf && <button className="btn btn-sm" onClick={() => openPdf(`/accounts/${node.id}/statement/pdf?with_attachments=1`)}>🖨 كشف</button>}</td>
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
  const [loading, setLoading] = useState(true)

  const load = async () => {
    setError(null)
    try {
      const [t, f] = await Promise.all([api.get('/accounts/tree'), api.get('/accounts')])
      setTree(t.data.data); setFlat(f.data.data)
    } catch (e) { setError(e.response?.data?.message || 'تعذّر التحميل') }
    finally { setLoading(false) }
  }
  useEffect(() => { load() }, [])

  const save = async (e) => {
    e.preventDefault(); setError(null)
    try {
      await api.post('/accounts', { ...form, parent_id: form.parent_id || null, opening_balance: Number(form.opening_balance || 0) })
      setForm({ type: 'ASSET', opening_balance_type: 'DEBIT' }); load()
    } catch (e) { setError(e.response?.data?.message || 'تعذّر الحفظ') }
  }

  return (
    <div>
      <div className="card">
        <h4>إضافة حساب</h4>
        <form onSubmit={save} className="row">
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
            <option value="DEBIT">مدين</option><option value="CREDIT">دائن</option>
          </select>
          <button className="btn-success" type="submit">+ إضافة حساب</button>
        </form>
        {error && <p className="err">{error}</p>}
      </div>

      <div className="card">
        {loading ? <SkeletonTable cols={6} rows={6} /> : (
        <table>
          <thead><tr><th>الحساب</th><th>النوع</th><th>الطبيعة</th><th>يظهر في</th><th style={{ textAlign: 'end' }}>الرصيد</th><th>كشف</th></tr></thead>
          <tbody>
            {tree.map((nd) => <Node key={nd.id} node={nd} depth={0} />)}
            {tree.length === 0 && <tr><td colSpan={6} className="muted">لا توجد حسابات</td></tr>}
          </tbody>
        </table>
        )}
      </div>
    </div>
  )
}
