import { useEffect, useState } from 'react'
import api from './api.js'
import Settings from './Settings.jsx'
import Accounts from './Accounts.jsx'
import JournalEntry from './JournalEntry.jsx'
import Vouchers from './Vouchers.jsx'
import PettyCash from './PettyCash.jsx'
import Settlement from './Settlement.jsx'
import Reports from './Reports.jsx'
import Dashboard from './Dashboard.jsx'
import FixedAssets from './FixedAssets.jsx'
import Banks from './Banks.jsx'
import Inventory from './Inventory.jsx'
import { Purchases, Sales } from './Trade.jsx'
import Hr from './Hr.jsx'
import Pos from './Pos.jsx'
import Reminders from './Reminders.jsx'

// مجموعات قائمة التنقّل
const NAV = [
  { group: 'لوحة', items: [['dashboard', 'المؤشرات', '📊']] },
  { group: 'المحاسبة', items: [
    ['accounts', 'دليل الحسابات', '🌳'],
    ['journal', 'قيد يومية', '📝'],
    ['vouchers', 'السندات', '🧾'],
  ] },
  { group: 'العمليات', items: [
    ['pettycash', 'العهد', '💼'],
    ['settlement', 'الأمانات والسداد', '🔴'],
    ['assets', 'الأصول الثابتة', '🏗️'],
    ['banks', 'البنوك والتسويات', '🏦'],
    ['inventory', 'المخزون', '📦'],
    ['purchases', 'المشتريات', '🛒'],
    ['sales', 'المبيعات', '🧾'],
    ['hr', 'الموارد البشرية', '👥'],
    ['pos', 'نقطة البيع', '🛍️'],
  ] },
  { group: 'التقارير', items: [['reports', 'التقارير', '📑']] },
  { group: 'النظام', items: [['reminders', 'التذكيرات', '🔔'], ['settings', 'الإعدادات', '⚙️']] },
]

const SCREENS = { dashboard: Dashboard, accounts: Accounts, journal: JournalEntry,
  vouchers: Vouchers, pettycash: PettyCash, settlement: Settlement, reports: Reports,
  settings: Settings, assets: FixedAssets, banks: Banks, inventory: Inventory,
  purchases: Purchases, sales: Sales, hr: Hr, pos: Pos, reminders: Reminders }

const TITLES = Object.fromEntries(NAV.flatMap((g) => g.items.map(([k, label, ico]) => [k, { label, ico }])))

// وصف موجز لكل موديول يظهر في شريط الرأس
const DESC = {
  dashboard: 'نظرة عامة على المؤشرات المالية', accounts: 'الشجرة المحاسبية وأرصدة الحسابات',
  journal: 'إدخال ومراجعة القيود اليومية', vouchers: 'سندات القبض والصرف والتحويل',
  pettycash: 'كشوف العهد وتحويلها لسندات', settlement: 'مطابقة الأمانات بالاستحقاقات',
  assets: 'تسجيل الأصول وحساب الإهلاك', banks: 'تسوية الحسابات البنكية',
  inventory: 'الأصناف والمخازن وحركة المخزون', purchases: 'فواتير الموردين',
  sales: 'فواتير العملاء', hr: 'الموظفون ومسير الرواتب', pos: 'البيع النقدي السريع',
  reports: 'القوائم المالية والتقارير', settings: 'إعدادات النظام الأساسية',
  reminders: 'تنبيهات الاستحقاقات وإعادة الطلب',
}

export default function App() {
  const [user, setUser] = useState(null)
  const [section, setSection] = useState('dashboard')
  const [email, setEmail] = useState('admin@noor.test')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)
  const [remCount, setRemCount] = useState(0)

  useEffect(() => {
    document.documentElement.dir = 'rtl'; document.documentElement.lang = 'ar'
    if (localStorage.getItem('token')) api.get('/me').then((r) => setUser(r.data.data)).catch(() => {})
  }, [])

  useEffect(() => {
    if (!user) return
    api.get('/reminders/due').then((r) => setRemCount(r.data.data.count)).catch(() => {})
  }, [user, section])

  const login = async (e) => {
    e.preventDefault(); setError(null); setLoading(true)
    try {
      const r = await api.post('/login', { email, password })
      localStorage.setItem('token', r.data.data.token)
      setUser(r.data.data.user)
    } catch (err) { setError(err.response?.data?.message || 'تعذّر الدخول') }
    finally { setLoading(false) }
  }

  const logout = async () => { try { await api.post('/logout') } catch {} localStorage.removeItem('token'); setUser(null) }

  if (!user) {
    return (
      <div className="login-wrap">
        <form className="login-card" onSubmit={login}>
          <div className="brand"><span className="logo">📒</span> نظام المحاسبة</div>
          <p className="muted" style={{ textAlign: 'center', marginTop: -6, marginBottom: 22 }}>سجّل الدخول للمتابعة</p>
          <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="البريد الإلكتروني" />
          <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="كلمة المرور" />
          <button className="btn-primary" type="submit" disabled={loading}>{loading ? '...' : 'دخول'}</button>
          {error && <p className="err" style={{ textAlign: 'center', marginTop: 12 }}>{error}</p>}
        </form>
      </div>
    )
  }

  const Screen = SCREENS[section]
  const initial = (user.name || '?').trim().charAt(0)
  const meta = TITLES[section]

  return (
    <div className="app" data-module={section}>
      <aside className="sidebar">
        <div className="brand"><span className="logo">📒</span> نظام المحاسبة</div>
        {NAV.map((g) => (
          <div key={g.group}>
            <div className="nav-group">{g.group}</div>
            {g.items.map(([k, label, ico]) => (
              <button key={k} className={`nav-item ${section === k ? 'active' : ''}`} onClick={() => setSection(k)}>
                <span className="ico">{ico}</span> {label}
              </button>
            ))}
          </div>
        ))}
      </aside>

      <div className="main">
        <header className="topbar">
          <div className="title">{meta.label}</div>
          <div className="right">
            <button className="btn-ghost" onClick={() => setSection('reminders')} title="التذكيرات"
              style={{ position: 'relative', fontSize: 18 }}>
              🔔
              {remCount > 0 && <span style={{ position: 'absolute', top: -2, insetInlineEnd: -2, background: '#ef4444', color: '#fff', borderRadius: 999, fontSize: 10, fontWeight: 700, minWidth: 16, height: 16, display: 'grid', placeItems: 'center', padding: '0 3px' }}>{remCount}</span>}
            </button>
            <span className="chip">🏢 {user.company?.name}</span>
            <span className="chip">{user.role?.name}</span>
            <div className="avatar">{initial}</div>
            <button className="btn-ghost btn-sm" onClick={logout}>خروج</button>
          </div>
        </header>
        <main className="content">
          <div className="page-banner">
            <span className="pb-ico">{meta.ico}</span>
            <div><h2>{meta.label}</h2><p>{DESC[section]}</p></div>
          </div>
          <Screen />
        </main>
      </div>
    </div>
  )
}
