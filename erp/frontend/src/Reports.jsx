import { useEffect, useState } from 'react'
import api from './api.js'

// التقارير المالية: ميزان مراجعة · قائمة الدخل · الميزانية · التدفقات · الدعاوى. + تصدير CSV.
const TABS = [
  ['trial-balance', 'ميزان المراجعة'],
  ['income-statement', 'قائمة الدخل'],
  ['balance-sheet', 'الميزانية العمومية'],
  ['cash-flow', 'التدفقات النقدية'],
  ['claims', 'تقرير الدعاوى'],
]

function exportCsv(filename, headers, rows) {
  const lines = [headers.join(',')]
  rows.forEach((r) => lines.push(r.map((c) => `"${c ?? ''}"`).join(',')))
  const blob = new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob); a.download = filename; a.click()
}

export default function Reports() {
  const [tab, setTab] = useState('trial-balance')
  const [data, setData] = useState(null)
  const [claimsMode, setClaimsMode] = useState('detailed')
  const [err, setErr] = useState(null)

  const load = async () => {
    setErr(null); setData(null)
    try {
      const url = tab === 'claims' ? `/reports/claims?mode=${claimsMode}` : `/reports/${tab}`
      const r = await api.get(url)
      setData(r.data.data)
    } catch (e) { setErr(e.response?.data?.message || 'تعذّر التحميل') }
  }
  useEffect(() => { load() }, [tab, claimsMode])

  return (
    <div style={{ maxWidth: 1000, margin: '20px auto', fontFamily: 'system-ui' }}>
      <h3>التقارير المالية</h3>
      <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 12 }}>
        {TABS.map(([k, label]) => (
          <button key={k} onClick={() => setTab(k)} style={btn(tab === k ? '#1d4ed8' : '#e5e7eb', tab === k ? '#fff' : '#111')}>{label}</button>
        ))}
      </div>
      {err && <p style={{ color: 'crimson' }}>{err}</p>}
      {data && tab === 'trial-balance' && <TrialBalance d={data} />}
      {data && tab === 'income-statement' && <Income d={data} />}
      {data && tab === 'balance-sheet' && <BalanceSheet d={data} />}
      {data && tab === 'cash-flow' && <CashFlow d={data} />}
      {data && tab === 'claims' && <Claims d={data} mode={claimsMode} setMode={setClaimsMode} />}
    </div>
  )
}

function TrialBalance({ d }) {
  return (
    <>
      <button onClick={() => exportCsv('trial-balance.csv', ['الرمز', 'الحساب', 'مدين', 'دائن'],
        d.rows.map((r) => [r.code, r.name, r.debit, r.credit]))} style={btn('#16a34a')}>تصدير CSV</button>
      <Table head={['الرمز', 'الحساب', 'مدين', 'دائن']}
        rows={d.rows.map((r) => [r.code, r.name, n(r.debit), n(r.credit)])}
        foot={['', 'الإجمالي', n(d.total_debit), n(d.total_credit)]} />
      <p style={{ color: d.balanced ? '#16a34a' : '#dc2626' }}>{d.balanced ? '✓ متوازن' : '✗ غير متوازن'}</p>
    </>
  )
}

function Income({ d }) {
  return (
    <div style={cardWrap}>
      <h4>الإيرادات</h4>
      <Table head={['الحساب', 'المبلغ']} rows={d.revenues.map((r) => [r.name, n(r.balance)])} foot={['الإجمالي', n(d.total_revenues)]} />
      <h4>المصروفات</h4>
      <Table head={['الحساب', 'المبلغ']} rows={d.expenses.map((r) => [r.name, n(r.balance)])} foot={['الإجمالي', n(d.total_expenses)]} />
      <h3 style={{ color: d.net_profit >= 0 ? '#16a34a' : '#dc2626' }}>
        صافي {d.net_profit >= 0 ? 'الربح' : 'الخسارة'}: {n(d.net_profit)}
      </h3>
    </div>
  )
}

function BalanceSheet({ d }) {
  return (
    <div style={cardWrap}>
      <h4>الأصول</h4>
      <Table head={['الحساب', 'المبلغ']} rows={d.assets.map((r) => [r.name, n(r.balance)])} foot={['إجمالي الأصول', n(d.total_assets)]} />
      <h4>الخصوم</h4>
      <Table head={['الحساب', 'المبلغ']} rows={d.liabilities.map((r) => [r.name, n(r.balance)])} foot={['إجمالي الخصوم', n(d.total_liabilities)]} />
      <h4>حقوق الملكية</h4>
      <Table head={['الحساب', 'المبلغ']} rows={[...d.equity.map((r) => [r.name, n(r.balance)]), ['صافي نتيجة الفترة', n(d.net_profit)]]}
        foot={['إجمالي حقوق الملكية + النتيجة', n(d.total_equity_with_result)]} />
      <p style={{ color: d.balanced ? '#16a34a' : '#dc2626' }}>
        {d.balanced ? '✓ متوازنة: الأصول = الخصوم + حقوق الملكية' : '✗ غير متوازنة'}
      </p>
    </div>
  )
}

function CashFlow({ d }) {
  return (
    <>
      <Table head={['الرمز', 'الحساب', 'وارد', 'صادر', 'صافي']}
        rows={d.accounts.map((r) => [r.code, r.name, n(r.inflow), n(r.outflow), n(r.net)])}
        foot={['', 'الإجمالي', n(d.total_inflow), n(d.total_outflow), n(d.net_cash_flow)]} />
    </>
  )
}

function Claims({ d, mode, setMode }) {
  const grouped = mode === 'grouped'
  return (
    <>
      <div style={{ marginBottom: 8 }}>
        <button onClick={() => setMode('detailed')} style={btn(!grouped ? '#1d4ed8' : '#e5e7eb', !grouped ? '#fff' : '#111')}>تفصيلي</button>
        <button onClick={() => setMode('grouped')} style={btn(grouped ? '#1d4ed8' : '#e5e7eb', grouped ? '#fff' : '#111')}>مجمّع</button>
        <button onClick={() => exportCsv('claims.csv',
          grouped ? ['العميل', 'المبلغ', 'المسدد', 'الباقي'] : ['العميل', 'الخصم', 'التاريخ', 'المبلغ', 'المسدد', 'الباقي'],
          d.rows.map((r) => grouped ? [r.customer, r.amount, r.paid, r.remaining] : [r.customer, r.counterparty_name, r.date, r.amount, r.paid, r.remaining]))}
          style={btn('#16a34a')}>تصدير CSV</button>
      </div>
      {grouped
        ? <Table head={['العميل', 'المبلغ', 'المسدد', 'الباقي']} rows={d.rows.map((r) => [r.customer, n(r.amount), n(r.paid), n(r.remaining)])} />
        : <Table head={['العميل', 'الخصم', 'التاريخ', 'البيان', 'المبلغ', 'المسدد', 'الباقي']}
            rows={d.rows.map((r) => [r.customer, r.counterparty_name, r.date, r.description, n(r.amount), n(r.paid), n(r.remaining)])} />}
    </>
  )
}

function Table({ head, rows, foot }) {
  return (
    <table style={{ width: '100%', borderCollapse: 'collapse', background: '#fff', fontSize: 13, marginBottom: 12 }}>
      <thead><tr>{head.map((h, i) => <th key={i} style={th}>{h}</th>)}</tr></thead>
      <tbody>
        {rows.map((r, i) => <tr key={i}>{r.map((c, j) => <td key={j} style={td}>{c}</td>)}</tr>)}
        {rows.length === 0 && <tr><td style={td} colSpan={head.length}>لا توجد بيانات</td></tr>}
      </tbody>
      {foot && <tfoot><tr>{foot.map((c, i) => <td key={i} style={{ ...td, fontWeight: 700, borderTop: '2px solid #999' }}>{c}</td>)}</tr></tfoot>}
    </table>
  )
}

const n = (v) => Number(v ?? 0).toFixed(3)
const btn = (bg, color = '#fff') => ({ background: bg, color, border: 0, borderRadius: 6, padding: '6px 14px', cursor: 'pointer', marginInlineEnd: 6 })
const cardWrap = { background: '#fff', padding: 16, borderRadius: 8 }
const th = { textAlign: 'start', padding: 8, borderBottom: '2px solid #e5e7eb' }
const td = { padding: 6, borderBottom: '1px solid #f0f0f0' }
