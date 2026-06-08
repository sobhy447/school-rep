import { useEffect, useState } from 'react'
import api from './api.js'

export default function Admin() {
  const [tab, setTab] = useState('users')
  return (
    <div>
      <div className="toolbar">
        {[['users', 'المستخدمون'], ['roles', 'الأدوار والصلاحيات'], ['company', 'الشركة']].map(([k, l]) => (
          <button key={k} className={`tab ${tab === k ? 'active' : ''}`} onClick={() => setTab(k)}>{l}</button>
        ))}
      </div>
      {tab === 'users' && <Users />}
      {tab === 'roles' && <Roles />}
      {tab === 'company' && <CompanyTab />}
    </div>
  )
}

function Users() {
  const [users, setUsers] = useState([]); const [roles, setRoles] = useState([])
  const [form, setForm] = useState({ is_active: true }); const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)
  const reload = async () => {
    const [u, r] = await Promise.all([api.get('/users'), api.get('/roles')])
    setUsers(u.data.data); setRoles(r.data.data)
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [])
  const save = async (e) => {
    e.preventDefault(); setErr(null); setMsg(null)
    try { await api.post('/users', form); setForm({ is_active: true }); setMsg('تم حفظ المستخدم'); reload() }
    catch (e) { setErr(e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'خطأ') }
  }
  const del = async (id) => { try { await api.delete(`/users/${id}`); reload() } catch (e) { setErr(e.response?.data?.message) } }
  return (
    <>
      <div className="card"><h4>إضافة مستخدم</h4>
        <form onSubmit={save} className="row">
          <input placeholder="الاسم" required value={form.name || ''} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <input placeholder="البريد" type="email" required value={form.email || ''} onChange={(e) => setForm({ ...form, email: e.target.value })} />
          <input placeholder="كلمة المرور" type="password" required value={form.password || ''} onChange={(e) => setForm({ ...form, password: e.target.value })} />
          <select required value={form.role_id || ''} onChange={(e) => setForm({ ...form, role_id: e.target.value })}>
            <option value="">— الدور —</option>{roles.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
          </select>
          <button className="btn-success" type="submit">+ إضافة</button>
        </form>
        {msg && <p className="ok">{msg}</p>}{err && <p className="err">{err}</p>}
      </div>
      <div className="card"><table>
        <thead><tr><th>الاسم</th><th>البريد</th><th>الدور</th><th>الحالة</th><th></th></tr></thead>
        <tbody>{users.map((u) => <tr key={u.id}><td>{u.name}</td><td>{u.email}</td><td>{u.role}</td>
          <td><span className={`badge ${u.is_active ? 'badge-green' : 'badge-gray'}`}>{u.is_active ? 'نشط' : 'معطّل'}</span></td>
          <td><button className="btn-danger btn-sm" onClick={() => del(u.id)}>حذف</button></td></tr>)}
          {users.length === 0 && <tr><td colSpan={5} className="muted">لا يوجد مستخدمون</td></tr>}</tbody>
      </table></div>
    </>
  )
}

function Roles() {
  const [roles, setRoles] = useState([]); const [groups, setGroups] = useState([])
  const [sel, setSel] = useState(null); const [form, setForm] = useState({ name: '', slug: '', permissions: [] })
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const reload = async () => {
    const [r, p] = await Promise.all([api.get('/roles'), api.get('/roles/permissions')])
    setRoles(r.data.data); setGroups(p.data.data)
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [])

  const edit = async (id) => {
    const r = await api.get(`/roles/${id}`)
    setSel(id); setForm({ name: r.data.data.name, slug: r.data.data.slug, permissions: r.data.data.permissions })
  }
  const newRole = () => { setSel(null); setForm({ name: '', slug: '', permissions: [] }) }
  const toggle = (key) => setForm((f) => ({ ...f, permissions: f.permissions.includes(key) ? f.permissions.filter((k) => k !== key) : [...f.permissions, key] }))
  const toggleModule = (items, on) => setForm((f) => {
    const keys = items.map((i) => i.key)
    return { ...f, permissions: on ? [...new Set([...f.permissions, ...keys])] : f.permissions.filter((k) => !keys.includes(k)) }
  })
  const save = async (e) => {
    e.preventDefault(); setErr(null); setMsg(null)
    try {
      if (sel) await api.put(`/roles/${sel}`, form); else await api.post('/roles', form)
      setMsg('تم الحفظ'); reload()
    } catch (e) { setErr(e.response?.data?.message || JSON.stringify(e.response?.data?.errors) || 'خطأ') }
  }

  return (
    <div style={{ display: 'grid', gridTemplateColumns: '240px 1fr', gap: 16 }}>
      <div className="card">
        <button className="btn-primary" style={{ width: '100%', marginBottom: 8 }} onClick={newRole}>+ دور جديد</button>
        {roles.map((r) => (
          <button key={r.id} className={`nav-item ${sel === r.id ? 'active' : ''}`} style={{ color: sel === r.id ? 'var(--accent-ink)' : 'inherit', background: sel === r.id ? 'var(--accent-soft)' : 'transparent', border: 0, width: '100%', textAlign: 'start', padding: 8, borderRadius: 8 }} onClick={() => edit(r.id)}>
            {r.name} <span className="muted">({r.permissions_count})</span>
          </button>
        ))}
      </div>
      <form className="card" onSubmit={save}>
        <h4>{sel ? 'تعديل دور' : 'دور جديد'}</h4>
        <div className="row" style={{ marginBottom: 10 }}>
          <input placeholder="اسم الدور" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          <input placeholder="المعرّف (slug)" required value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} />
        </div>
        {groups.map((g) => {
          const allOn = g.items.every((i) => form.permissions.includes(i.key))
          return (
            <div key={g.module} style={{ border: '1px solid var(--border)', borderRadius: 8, padding: 10, marginBottom: 8 }}>
              <label style={{ fontWeight: 700, color: 'var(--accent-ink)' }}>
                <input type="checkbox" checked={allOn} onChange={(e) => toggleModule(g.items, e.target.checked)} /> {g.module}
              </label>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, marginTop: 6 }}>
                {g.items.map((i) => (
                  <label key={i.key} style={{ fontSize: 12 }}>
                    <input type="checkbox" checked={form.permissions.includes(i.key)} onChange={() => toggle(i.key)} /> {i.label.split('—').pop()}
                  </label>
                ))}
              </div>
            </div>
          )
        })}
        <button className="btn-success" type="submit">💾 حفظ الدور</button>
        {msg && <p className="ok">{msg}</p>}{err && <p className="err">{err}</p>}
      </form>
    </div>
  )
}

function CompanyTab() {
  const [company, setCompany] = useState(null); const [companies, setCompanies] = useState([]); const [isSuper, setIsSuper] = useState(false)
  const [newco, setNewco] = useState({}); const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const reload = async () => {
    const me = await api.get('/me'); setIsSuper(me.data.data.is_super)
    const c = await api.get('/company'); setCompany(c.data.data)
    if (me.data.data.is_super) setCompanies((await api.get('/companies')).data.data)
  }
  useEffect(() => { reload().catch(() => setErr('تعذّر التحميل')) }, [])

  const saveCompany = async (e) => {
    e.preventDefault(); setErr(null); setMsg(null)
    try { await api.put('/company', { name: company.name, name_en: company.name_en, currency_code: company.currency_code }); setMsg('تم تحديث بيانات الشركة') }
    catch (e) { setErr(e.response?.data?.message) }
  }
  const createCo = async (e) => {
    e.preventDefault(); setErr(null); setMsg(null)
    try { await api.post('/companies', newco); setNewco({}); setMsg('تم إنشاء الشركة'); reload() }
    catch (e) { setErr(e.response?.data?.message || JSON.stringify(e.response?.data?.errors)) }
  }
  const switchCo = async (id) => { await api.post(`/companies/${id}/switch`); window.location.reload() }

  if (!company) return <p className="muted">…</p>
  return (
    <>
      <form className="card" onSubmit={saveCompany}>
        <h4>بيانات الشركة الحالية</h4>
        <div className="row">
          <input placeholder="الاسم" value={company.name || ''} onChange={(e) => setCompany({ ...company, name: e.target.value })} />
          <input placeholder="الاسم (EN)" value={company.name_en || ''} onChange={(e) => setCompany({ ...company, name_en: e.target.value })} />
          <input placeholder="العملة" style={{ width: 80 }} value={company.currency_code || ''} onChange={(e) => setCompany({ ...company, currency_code: e.target.value })} />
          <button className="btn-success" type="submit">حفظ</button>
        </div>
        {msg && <p className="ok">{msg}</p>}{err && <p className="err">{err}</p>}
      </form>

      {isSuper && (
        <>
          <div className="card"><h4>الشركات (تبديل)</h4>
            <table><thead><tr><th>الرمز</th><th>الاسم</th><th></th></tr></thead>
              <tbody>{companies.map((c) => <tr key={c.id}><td>{c.code}</td><td>{c.name}</td><td><button className="btn btn-sm" onClick={() => switchCo(c.id)}>تبديل</button></td></tr>)}</tbody>
            </table>
          </div>
          <form className="card" onSubmit={createCo}><h4>إنشاء شركة جديدة (مع تجهيز شجرة الحسابات والمدير)</h4>
            <div className="row">
              <input placeholder="رمز الشركة" required value={newco.code || ''} onChange={(e) => setNewco({ ...newco, code: e.target.value })} />
              <input placeholder="اسم الشركة" required value={newco.name || ''} onChange={(e) => setNewco({ ...newco, name: e.target.value })} />
              <input placeholder="اسم المدير" required value={newco.admin_name || ''} onChange={(e) => setNewco({ ...newco, admin_name: e.target.value })} />
              <input placeholder="بريد المدير" type="email" required value={newco.admin_email || ''} onChange={(e) => setNewco({ ...newco, admin_email: e.target.value })} />
              <input placeholder="كلمة مرور المدير" type="password" required value={newco.admin_password || ''} onChange={(e) => setNewco({ ...newco, admin_password: e.target.value })} />
              <button className="btn-success" type="submit">+ إنشاء وتجهيز</button>
            </div>
          </form>
        </>
      )}
    </>
  )
}
