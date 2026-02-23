import { defineStore } from 'pinia'

let counter = 0
const DEFAULT_DEDUPE_WINDOW_MS = 2000

export const useToastStore = defineStore('toast', {
  state: () => ({
    toasts: [],
    lastShownAtByKey: {},
  }),
  actions: {
    push(payload) {
      const now = Date.now()
      const dedupeKey =
        payload.dedupeKey ?? `${payload.type ?? 'info'}|${payload.title ?? 'Notice'}|${payload.message ?? ''}`
      const dedupeWindow =
        Number.isFinite(Number(payload.dedupeWindow)) && Number(payload.dedupeWindow) >= 0
          ? Number(payload.dedupeWindow)
          : DEFAULT_DEDUPE_WINDOW_MS

      if (dedupeWindow > 0) {
        const activeDuplicate = this.toasts.find((toast) => toast.dedupeKey === dedupeKey)

        if (activeDuplicate) {
          return activeDuplicate.id
        }

        const lastShownAt = this.lastShownAtByKey[dedupeKey] ?? 0

        if (now - lastShownAt < dedupeWindow) {
          return null
        }
      }

      const id = `${Date.now()}-${counter++}`
      const toast = {
        id,
        type: payload.type ?? 'info',
        title: payload.title ?? 'Notice',
        message: payload.message ?? '',
        duration: payload.duration ?? 4000,
        dedupeKey,
      }

      this.toasts.push(toast)
      this.lastShownAtByKey[dedupeKey] = now

      if (toast.duration > 0) {
        window.setTimeout(() => {
          this.remove(id)
        }, toast.duration)
      }

      return id
    },
    success(message, title = 'Success') {
      this.push({ type: 'success', title, message, dedupeWindow: 1200 })
    },
    error(message, title = 'Request failed') {
      this.push({ type: 'danger', title, message, duration: 4000, dedupeWindow: 2500 })
    },
    warning(message, title = 'Warning') {
      this.push({ type: 'warning', title, message, dedupeWindow: 2000 })
    },
    info(message, title = 'Notice') {
      this.push({ type: 'info', title, message, dedupeWindow: 1500 })
    },
    remove(id) {
      this.toasts = this.toasts.filter((toast) => toast.id !== id)
    },
    clear() {
      this.toasts = []
      this.lastShownAtByKey = {}
    },
  },
})
