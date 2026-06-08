import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// وجهة الـ API: محلياً localhost، وداخل Docker تُضبط VITE_API_PROXY=http://api:8000
const apiTarget = process.env.VITE_API_PROXY || 'http://localhost:8000'

export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 3000,
    proxy: {
      '/api': { target: apiTarget, changeOrigin: true },
    },
  },
})
