import { defineStore } from 'pinia'

let counter = 0

export const useToastStore = defineStore('toast', {
  state: () => ({
    toasts: [],
  }),
  actions: {
    push(payload) {
      const id = `${Date.now()}-${counter++}`
      const toast = {
        id,
        type: payload.type ?? 'info',
        title: payload.title ?? 'Notice',
        message: payload.message ?? '',
        duration: payload.duration ?? 4000,
      }

      this.toasts.push(toast)

      if (toast.duration > 0) {
        window.setTimeout(() => {
          this.remove(id)
        }, toast.duration)
      }

      return id
    },
    success(message, title = 'Success') {
      this.push({ type: 'success', title, message })
    },
    error(message, title = 'Request failed') {
      this.push({ type: 'danger', title, message, duration: 4000 })
    },
    warning(message, title = 'Warning') {
      this.push({ type: 'warning', title, message })
    },
    info(message, title = 'Notice') {
      this.push({ type: 'info', title, message })
    },
    remove(id) {
      this.toasts = this.toasts.filter((toast) => toast.id !== id)
    },
    clear() {
      this.toasts = []
    },
  },
})
