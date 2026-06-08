import { useEffect, useState } from 'react'
import api from './api.js'
import { SkeletonTable } from './Skeleton.jsx'

const TABS = [
  ['trial-balance', 'ميزان المراجعة'],
  ['income-statement', 'قائمة الدخل'],
  ['balance-sheet', 'الميزانية العمومية'],
  ['cash-flow', 'التدفقات النقدية'],
  ['claims', 'تقرير الدعاوى'],
  ['aging', 'أعمار الديون'],
  ['vat', 'ضريبة القيمة المضافة'],
]

function exportCsv(filename, headers, rows) {
  const lines = [headers.join(',')]
  rows.forEach((r) => lines.push(r.map((c) => `"${c ?? ''}"`).join(',')))
  const blob = new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob); a.download = filename; a.click()
}

const n = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 })

export default function Reports() {
  const [tab, setTab] = useState('trial-balance')
  const [data, setData] = useState(null)
  const [claimsMode, setClaimsMode] = useState('detailed')
  const [err, setErr] = useState(null)

  const load = async () => {
    setErr(null); setData(null)
    try {
      const url = tab === 'claims' ? `/reports/claims?mode=${claimsMode}` : (tab === 'aging' ? '/reports/aging?type=CUSTOMER' : `/reports/${tab}`)
      const r = await api.get(url); setData(r.data.data)
    } catch (e) { setErr(e.response?.data?.message || 'تعذّر التحميل') }
  }
  useEffect(() => { load() }, [tab, claimsMode])

  return (
    <div>
      <div className="toolbar">
        {TABS.map(([k, label]) => (
          <button key={k} className={`tab ${tab === k ? 'active' : ''}`} onClick={() => setTab(k)}>{label}</button>
        ))}
      </div>
      {err && <p className="err">{err}</p>}
      <div className="card">
        {data && tab === 'trial-balance' && <TrialBalance d={data} />}
        {data && tab === 'income-statement' && <Income d={data} />}
        {data && tab === 'balance-sheet' && <BalanceSheet d={data} />}
        {data && tab === 'cash-flow' && <CashFlow d={data} />}
        {data && tab === 'claims' && <Claims d={data} mode={claimsMode} setMode={setClaimsMode} />}
        {data && tab === 'aging' && <Aging d={data} />}
        {data && tab === 'vat' && <Vat d={data} />}
        {!data && !err && <SkeletonTable cols={4} rows={7} />}
      </div>
    </div>
  )
}

function Aging({ d }) {
  const t = d.totals
  return (
    <Table head={['الطرف', 'حالي (0-30)', '31-60', '61-90', '91-120', 'أقدم', 'الإجمالي']}
      rows={d.rows.map((r) => [r.name, n(r.current), n(r.d30), n(r.d60), n(r.d90), n(r.older), n(r.total)])}
      foot={['الإجمالي', n(t.current), n(t.d30), n(t.d60), n(t.d90), n(t.older), n(t.total)]} />
  )
}

function Vat({ d }) {
  return (
    <Table head={['البند', 'المبلغ']} rows={[
      ['ضريبة المخرجات (مبيعات)', n(d.output_tax)],
      ['ضريبة المدخلات (مشتريات)', n(d.input_tax)],
      [d.status, n(d.net_vat)],
    ]} />
  )
}

function Table({ head, rows, foot }) {
  return (
    <table>
      <thead><tr>{head.map((h, i) => <th key={i}>{h}</th>)}</tr></thead>
      <tbody>
        {rows.map((r, i) => <tr key={i}>{r.map((c, j) => <td key={j}>{c}</td>)}</tr>)}
        {rows.length === 0 && <tr><td colSpan={head.length} className="muted">لا توجد بيانات</td></tr>}
      </tbody>
      {foot && <tfoot><tr>{foot.map((c, i) => <td key={i}>{c}</td>)}</tr></tfoot>}
    </table>
  )
}

function TrialBalance({ d }) {
  return (
    <>
      <div className="toolbar">
        <button className="btn-success btn-sm" onClick={() => exportCsv('trial-balance.csv', ['الرمز', 'الحساب', 'مدين', 'دائن'], d.rows.map((r) => [r.code, r.name, r.debit, r.credit]))}>⬇ تصدير CSV</button>
        <span className={`badge ${d.balanced ? 'badge-green' : 'badge-red'}`}>{d.balanced ? '✓ متوازن' : '✗ غير متوازن'}</span>
      </div>
      <Table head={['الرمز', 'الحساب', 'مدين', 'دائن']} rows={d.rows.map((r) => [r.code, r.name, n(r.debit), n(r.credit)])} foot={['', 'الإجمالي', n(d.total_debit), n(d.total_credit)]} />
    </>
  )
}

function Income({ d }) {
  return (
    <>
      <h4>الإيرادات</h4>
      <Table head={['الحساب', 'المبلغ']} rows={d.revenues.map((r) => [r.name, n(r.balance)])} foot={['الإجمالي', n(d.total_revenues)]} />
      <h4 style={{ marginTop: 18 }}>المصروفات</h4>
      <Table head={['الحساب', 'المبلغ']} rows={d.expenses.map((r) => [r.name, n(r.balance)])} foot={['الإجمالي', n(d.total_expenses)]} />
      <h3 style={{ marginTop: 18, color: d.net_profit >= 0 ? 'var(--success)' : 'var(--danger)' }}>صافي {d.net_profit >= 0 ? 'الربح' : 'الخسارة'}: {n(d.net_profit)} د.ك</h3>
    </>
  )
}

function BalanceSheet({ d }) {
  return (
    <>
      <h4>الأصول</h4>
      <Table head={['الحساب', 'المبلغ']} rows={d.assets.map((r) => [r.name, n(r.balance)])} foot={['إجمالي الأصول', n(d.total_assets)]} />
      <h4 style={{ marginTop: 18 }}>الخصوم</h4>
      <Table head={['الحساب', 'المبلغ']} rows={d.liabilities.map((r) => [r.name, n(r.balance)])} foot={['إجمالي الخصوم', n(d.total_liabilities)]} />
      <h4 style={{ marginTop: 18 }}>حقوق الملكية</h4>
      <Table head={['الحساب', 'المبلغ']} rows={[...d.equity.map((r) => [r.name, n(r.balance)]), ['صافي نتيجة الفترة', n(d.net_profit)]]} foot={['الإجمالي + النتيجة', n(d.total_equity_with_result)]} />
      <p style={{ marginTop: 14 }}><span className={`badge ${d.balanced ? 'badge-green' : 'badge-red'}`}>{d.balanced ? '✓ متوازنة: الأصول = الخصوم + حقوق الملكية' : '✗ غير متوازنة'}</span></p>
    </>
  )
}

function CashFlow({ d }) {
  return <Table head={['الرمز', 'الحساب', 'وارد', 'صادر', 'صافي']} rows={d.accounts.map((r) => [r.code, r.name, n(r.inflow), n(r.outflow), n(r.net)])} foot={['', 'الإجمالي', n(d.total_inflow), n(d.total_outflow), n(d.net_cash_flow)]} />
}

function Claims({ d, mode, setMode }) {
  const grouped = mode === 'grouped'
  return (
    <>
      <div className="toolbar">
        <button className={`tab ${!grouped ? 'active' : ''}`} onClick={() => setMode('detailed')}>تفصيلي</button>
        <button className={`tab ${grouped ? 'active' : ''}`} onClick={() => setMode('grouped')}>مجمّع</button>
        <button className="btn-success btn-sm" onClick={() => exportCsv('claims.csv',
          grouped ? ['العميل', 'المبلغ', 'المسدد', 'الباقي'] : ['العميل', 'الخصم', 'التاريخ', 'المبلغ', 'المسدد', 'الباقي'],
          d.rows.map((r) => grouped ? [r.customer, r.amount, r.paid, r.remaining] : [r.customer, r.counterparty_name, r.date, r.amount, r.paid, r.remaining]))}>⬇ تصدير CSV</button>
      </div>
      {grouped
        ? <Table head={['العميل', 'المبلغ', 'المسدد', 'الباقي']} rows={d.rows.map((r) => [r.customer, n(r.amount), n(r.paid), n(r.remaining)])} />
        : <Table head={['العميل', 'الخصم', 'التاريخ', 'البيان', 'المبلغ', 'المسدد', 'الباقي']} rows={d.rows.map((r) => [r.customer, r.counterparty_name, r.date, r.description, n(r.amount), n(r.paid), n(r.remaining)])} />}
    </>
  )
}
