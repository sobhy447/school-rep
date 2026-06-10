import { useEffect, useState } from 'react'
import api from './api.js'

// نافذة المرفقات: رفع PDF للسند/القيد + ربط كل سطر بأرقام صفحاته.
export default function AttachmentsModal({ entryId, onClose }) {
  const [entry, setEntry] = useState(null)
  const [attachments, setAttachments] = useState([])
  const [pages, setPages] = useState({})   // line_id -> {page_from, multi, page_to}
  const [file, setFile] = useState(null)
  const [totalPages, setTotalPages] = useState('')
  const [msg, setMsg] = useState(null); const [err, setErr] = useState(null)

  const load = async () => {
    const [e, a] = await Promise.all([api.get(`/journal-entries/${entryId}`), api.get(`/journal-entries/${entryId}/attachments`)])
    setEntry(e.data.data); setAttachments(a.data.data)
    const init = {}
    e.data.data.lines.forEach((l) => { init[l.id] = { page_from: l.page_from || '', multi: l.page_to && l.page_to !== l.page_from, page_to: l.page_to || '' } })
    setPages(init)
  }
  useEffect(() => { load().catch(() => setErr('تعذّر التحميل')) }, [entryId])

  const upload = async (e) => {
    e.preventDefault(); setErr(null); setMsg(null)
    if (!file) return
    const fd = new FormData(); fd.append('file', file); if (totalPages) fd.append('total_pages', totalPages)
    try {
      await api.post(`/journal-entries/${entryId}/attachments`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      setFile(null); setTotalPages(''); setMsg('تم رفع الملف'); load()
    } catch (e) { setErr(e.response?.data?.message || 'تعذّر الرفع') }
  }

  const savePages = async () => {
    setErr(null); setMsg(null)
    const lines = Object.entries(pages).filter(([, v]) => v.page_from).map(([line_id, v]) => ({
      line_id: Number(line_id), page_from: Number(v.page_from), page_to: v.multi && v.page_to ? Number(v.page_to) : Number(v.page_from),
    }))
    try { await api.post(`/journal-entries/${entryId}/line-pages`, { lines }); setMsg('تم ربط الصفحات بالسطور'); load() }
    catch (e) { setErr(e.response?.data?.message || 'تعذّر الحفظ') }
  }

  const download = (id) => window.open(`/api/attachments/${id}/download`, '_blank')
  const setP = (id, patch) => setPages((p) => ({ ...p, [id]: { ...p[id], ...patch } }))

  return (
    <div style={overlay} onClick={onClose}>
      <div className="card" style={modal} onClick={(e) => e.stopPropagation()}>
        <div className="row" style={{ justifyContent: 'space-between' }}>
          <h3>📎 مرفقات: {entry?.entry_number}</h3>
          <button className="btn-ghost" onClick={onClose}>✕ إغلاق</button>
        </div>

        <form onSubmit={upload} className="row" style={{ background: 'var(--accent-soft)', padding: 12, borderRadius: 10 }}>
          <input type="file" accept="application/pdf" onChange={(e) => setFile(e.target.files[0])} />
          <input type="number" placeholder="عدد صفحات الملف" value={totalPages} onChange={(e) => setTotalPages(e.target.value)} style={{ width: 140 }} />
          <button className="btn-success" type="submit">⬆ رفع PDF</button>
        </form>

        {attachments.length > 0 && (
          <table style={{ marginTop: 12 }}>
            <thead><tr><th>الملف</th><th>الصفحات</th><th></th></tr></thead>
            <tbody>{attachments.map((a) => <tr key={a.id}><td>{a.file_name}</td><td>{a.total_pages || '—'}</td><td><button className="btn btn-sm" onClick={() => download(a.id)}>عرض</button></td></tr>)}</tbody>
          </table>
        )}

        <h4 style={{ marginTop: 16 }}>ربط صفحات كل سطر</h4>
        <table>
          <thead><tr><th>السطر / البيان</th><th>مدين</th><th>دائن</th><th>الصفحة</th><th>عدة صفحات؟</th><th>من — إلى</th></tr></thead>
          <tbody>
            {entry?.lines.map((l) => (
              <tr key={l.id}>
                <td>{l.description || l.account?.name || `سطر ${l.line_number}`}</td>
                <td>{Number(l.debit).toFixed(3)}</td><td>{Number(l.credit).toFixed(3)}</td>
                <td><input type="number" min="1" style={{ width: 70 }} value={pages[l.id]?.page_from || ''} onChange={(e) => setP(l.id, { page_from: e.target.value })} /></td>
                <td><input type="checkbox" checked={!!pages[l.id]?.multi} onChange={(e) => setP(l.id, { multi: e.target.checked })} /></td>
                <td>{pages[l.id]?.multi && (
                  <span>من {pages[l.id]?.page_from || '?'} إلى <input type="number" min="1" style={{ width: 60 }} value={pages[l.id]?.page_to || ''} onChange={(e) => setP(l.id, { page_to: e.target.value })} /></span>
                )}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="toolbar" style={{ marginTop: 10 }}>
          <button className="btn-primary" onClick={savePages}>💾 حفظ ربط الصفحات</button>
        </div>
        {msg && <p className="ok">{msg}</p>}{err && <p className="err">{err}</p>}
      </div>
    </div>
  )
}

const overlay = { position: 'fixed', inset: 0, background: 'rgba(15,23,42,.5)', display: 'grid', placeItems: 'start center', padding: 24, zIndex: 50, overflowY: 'auto' }
const modal = { width: '100%', maxWidth: 760, marginTop: 20 }
