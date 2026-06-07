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
  ] },
  { group: 'التقارير', items: [['reports', 'التقارير', '📑']] },
  { group: 'النظام', items: [['settings', 'الإعدادات', '⚙️']] },
]

const SCREENS = { dashboard: Dashboard, accounts: Accounts, journal: JournalEntry,
  vouchers: Vouchers, pettycash: PettyCash, settlement: Settlement, reports: Reports, settings: Settings }

const TITLES = Object.fromEntries(NAV.flatMap((g) => g.items.map(([k, label]) => [k, label])))

export default function App() {
  const [user, setUser] = useState(null)
  const [section, setSection] = useState('dashboard')
  const [email, setEmail] = useState('admin@noor.test')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    document.documentElement.dir = 'rtl'; document.documentElement.lang = 'ar'
    if (localStorage.getItem('token')) api.get('/me').then((r) => setUser(r.data.data)).catch(() => {})
  }, [])

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

  return (
    <div className="app">
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
          <div className="title">{TITLES[section]}</div>
          <div className="right">
            <span className="chip">🏢 {user.company?.name}</span>
            <span className="chip">{user.role?.name}</span>
            <div className="avatar">{initial}</div>
            <button className="btn-ghost btn-sm" onClick={logout}>خروج</button>
          </div>
        </header>
        <main className="content">
          <Screen />
        </main>
      </div>
    </div>
  )
}
