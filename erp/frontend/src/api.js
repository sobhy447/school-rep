import axios from 'axios'

// عميل HTTP موحّد: يضيف التوكن تلقائياً ويتعامل مع 401.
const api = axios.create({ baseURL: '/api' })

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('token')
    }
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
