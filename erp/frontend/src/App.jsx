import { useEffect, useState } from 'react'
import api from './api.js'
import Settings from './Settings.jsx'
import Accounts from './Accounts.jsx'
import JournalEntry from './JournalEntry.jsx'
import Vouchers from './Vouchers.jsx'

// المرحلة 0: شاشة دخول + عرض المستخدم الحالي (إثبات الأساس يعمل).
const T = {
  ar: { title: 'نظام ERP المحاسبي', login: 'تسجيل الدخول', email: 'البريد', pass: 'كلمة المرور',
        signin: 'دخول', logout: 'خروج', welcome: 'مرحباً', company: 'الشركة', role: 'الدور', perms: 'الصلاحيات' },
  en: { title: 'Accounting ERP', login: 'Login', email: 'Email', pass: 'Password',
        signin: 'Sign in', logout: 'Logout', welcome: 'Welcome', company: 'Company', role: 'Role', perms: 'Permissions' },
}

export default function App() {
  const [lang, setLang] = useState('ar')
  const [user, setUser] = useState(null)
  const [section, setSection] = useState('settings')
  const [email, setEmail] = useState('admin@noor.test')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState(null)
  const t = T[lang]

  useEffect(() => {
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr'
    document.documentElement.lang = lang
  }, [lang])

  useEffect(() => {
    if (localStorage.getItem('token')) {
      api.get('/me').then((r) => setUser(r.data.data)).catch(() => {})
    }
  }, [])

  const login = async (e) => {
    e.preventDefault()
    setError(null)
    try {
      const r = await api.post('/login', { email, password })
      localStorage.setItem('token', r.data.data.token)
      setUser(r.data.data.user)
    } catch (err) {
      setError(err.response?.data?.message || 'خطأ')
    }
  }

  const logout = async () => {
    try { await api.post('/logout') } catch {}
    localStorage.removeItem('token')
    setUser(null)
  }

  const box = { maxWidth: 420, margin: '60px auto', fontFamily: 'system-ui, sans-serif',
                background: '#fff', padding: 24, borderRadius: 12, boxShadow: '0 4px 20px rgba(0,0,0,.08)' }

  return (
    <div style={{ minHeight: '100vh', background: '#f3f4f6' }}>
      <div style={box}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h2 style={{ margin: 0 }}>{t.title}</h2>
          <button onClick={() => setLang(lang === 'ar' ? 'en' : 'ar')}>{lang === 'ar' ? 'EN' : 'ع'}</button>
        </div>
        {!user ? (
          <form onSubmit={login} style={{ marginTop: 20, display: 'grid', gap: 12 }}>
            <h3>{t.login}</h3>
            <input value={email} onChange={(e) => setEmail(e.target.value)} placeholder={t.email} />
            <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder={t.pass} />
            <button type="submit">{t.signin}</button>
            {error && <p style={{ color: 'crimson' }}>{error}</p>}
          </form>
        ) : (
          <div style={{ marginTop: 20 }}>
            <h3>{t.welcome} {user.name}</h3>
            <p>{t.company}: {user.company?.name} — {t.role}: {user.role?.name} — {t.perms}: {user.permissions?.length}</p>
            <button onClick={logout}>{t.logout}</button>
          </div>
        )}
      </div>
      {user && (
        <>
          <div style={{ maxWidth: 1100, margin: '0 auto', display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            {[['settings', 'الإعدادات'], ['accounts', 'دليل الحسابات'], ['journal', 'قيد يومية'], ['vouchers', 'السندات']].map(([k, label]) => (
              <button key={k} onClick={() => setSection(k)}
                style={{ padding: '6px 14px', border: 0, borderRadius: 6, cursor: 'pointer',
                         background: section === k ? '#1d4ed8' : '#e5e7eb', color: section === k ? '#fff' : '#111' }}>
                {label}
              </button>
            ))}
          </div>
          {section === 'settings' && <Settings />}
          {section === 'accounts' && <Accounts />}
          {section === 'journal' && <JournalEntry />}
          {section === 'vouchers' && <Vouchers />}
        </>
      )}
    </div>
  )
}
