import axios from 'axios'
import { toast } from './toast.js'

// عميل HTTP موحّد: يضيف التوكن تلقائياً ويتعامل مع 401.
const api = axios.create({ baseURL: '/api' })

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// رسالة خطأ مقروءة من استجابة الـ API (تشمل أخطاء التحقّق).
function readError(err) {
  const d = err.response?.data
  if (d?.message) return d.message
  if (d?.errors) { const f = Object.values(d.errors)[0]; return Array.isArray(f) ? f[0] : f }
  if (err.response?.status === 401) return 'انتهت الجلسة — سجّل الدخول من جديد'
  return 'تعذّر الاتصال بالخادم'
}

api.interceptors.response.use(
  (res) => {
    // إشعار نجاح تلقائي لعمليات التعديل التي تُرجِع رسالة
    const method = (res.config?.method || 'get').toLowerCase()
    if (method !== 'get' && res.data?.message) toast.success(res.data.message)
    return res
  },
  (err) => {
    if (err.response?.status === 401) localStorage.removeItem('token')
    // لا نُظهر إشعار خطأ لمحاولة الدخول الفاشلة (تظهر داخل الشاشة)
    if (! err.config?.url?.endsWith('/login')) toast.error(readError(err))
    return Promise.reject(err)
  }
)

// يفتح ملف PDF محمي (يضيف التوكن ثم يفتحه في تبويب جديد)
export async function openPdf(path) {
  const res = await api.get(path, { responseType: 'blob' })
  const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
  window.open(url, '_blank')
}

export default api
