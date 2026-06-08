import { useEffect, useState } from 'react'
import api from './api.js'

// خرائط الترحيل: ربط كل عملية بحسابها المحاسبي بشكل مرئي.
const KEYS = [
  ['petty_cash_account_id', 'حساب العهدة'],
  ['income_summary_account_id', 'حساب النتيجة (الإقفال)'],
  ['retained_earnings_account_id', 'الأرباح المحتجزة'],
  ['vat_input_account_id', 'ضريبة المدخلات'],
  ['vat_output_account_id', 'ضريبة المخرجات'],
  ['salary_expense_account_id', 'مصروف الرواتب'],
  ['salaries_payable_account_id', 'الرواتب المستحقة'],
  ['deductions_payable_account_id', 'الاستقطاعات المستحقة'],
  ['cheques_collection_account_id', 'شيكات تحت التحصيل'],
  ['cheques_payable_account_id', 'شيكات الدفع'],
]

export default function PostingMap() {
  const [accounts, setAccounts] = useState([])
  const [values, setValues] = useState({})
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const load = async () => {
    const [a, s] = await Promise.all([api.get('/accounts'), api.get('/company-settings')])
    setAccounts(a.data.data.filter((x) => x.is_leaf))
    setValues(s.data.data || {})
  }
  useEffect(() => { load().catch(() => setErr('تعذّر التحميل')) }, [])

  const save = async () => {
    setErr(null); setMsg(null)
    try { await api.put('/company-settings', { settings: values }); setMsg('تم حفظ خرائط الترحيل') }
    catch (e) { setErr(e.response?.data?.message || 'تعذّر الحفظ') }
  }

  return (
    <div className="card">
      <h4>ربط العمليات بالحسابات المحاسبية</h4>
      <p className="muted">حدّد الحساب المستخدم لكل عملية تلقائية (العهد/الإقفال/الضريبة/الرواتب/الشيكات).</p>
      <table>
        <thead><tr><th>العملية</th><th>الحساب</th></tr></thead>
        <tbody>
          {KEYS.map(([k, label]) => (
            <tr key={k}>
              <td>{label}</td>
              <td>
                <select value={values[k] || ''} onChange={(e) => setValues({ ...values, [k]: e.target.value })} style={{ minWidth: 260 }}>
                  <option value="">— اختر حساباً —</option>
                  {accounts.map((a) => <option key={a.id} value={a.id}>{a.code} — {a.name}</option>)}
                </select>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      <div className="toolbar" style={{ marginTop: 12 }}><button className="btn-success" onClick={save}>💾 حفظ</button></div>
      {msg && <p className="ok">{msg}</p>}{err && <p className="err">{err}</p>}
    </div>
  )
}
