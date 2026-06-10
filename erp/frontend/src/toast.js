// نظام إشعارات منبثقة بسيط (Toast) — قابل للاستدعاء من أي مكان.
const listeners = new Set()

export function onToast(fn) {
  listeners.add(fn)
  return () => listeners.delete(fn)
}

function emit(type, message) {
  if (!message) return
  const t = { id: Date.now() + Math.random(), type, message: String(message) }
  listeners.forEach((l) => l(t))
}

export const toast = {
  success: (m) => emit('success', m),
  error: (m) => emit('error', m),
  info: (m) => emit('info', m),
}
