import { defineStore } from 'pinia'
import api from '@/lib/api'

const TOKEN_STORAGE_KEY = 'youos_token'
let initializeRequest = null

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: '',
    user: null,
    initializing: false,
    initialized: false,
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token),
  },
  actions: {
    setToken(token) {
      this.token = token ?? ''

      if (this.token) {
        window.localStorage.setItem(TOKEN_STORAGE_KEY, this.token)
      } else {
        window.localStorage.removeItem(TOKEN_STORAGE_KEY)
      }
    },
    setUser(user) {
      this.user = user ?? null
    },
    clearAuth() {
      this.setToken('')
      this.setUser(null)
    },
    async fetchUser() {
      const response = await api.get('/api/user')
      this.setUser(response.data)
      return response.data
    },
    async initialize() {
      if (this.initialized) {
        return
      }

      if (this.initializing && initializeRequest) {
        await initializeRequest
        return
      }

      this.initializing = true
      initializeRequest = (async () => {
        const persistedToken = window.localStorage.getItem(TOKEN_STORAGE_KEY) ?? ''
        this.token = persistedToken
        this.user = null

        try {
          if (this.token) {
            await this.fetchUser()
          }
        } catch (_error) {
          this.clearAuth()
        } finally {
          this.initialized = true
        }
      })()

      try {
        await initializeRequest
      } finally {
        this.initializing = false
        initializeRequest = null
      }
    },
    async login(payload) {
      const response = await api.post('/api/login', payload)
      const token = response.data?.token ?? response.data?.access_token ?? ''
      const user = response.data?.user ?? null

      if (!token) {
        throw {
          error: 'auth_token_missing',
          message: 'Login succeeded but no token was returned.',
        }
      }

      this.setToken(token)

      if (user) {
        this.setUser(user)
      } else {
        await this.fetchUser()
      }

      return this.user
    },
    async register(payload) {
      const response = await api.post('/api/register', payload)
      const token = response.data?.token ?? response.data?.access_token ?? ''
      const user = response.data?.user ?? null

      if (token) {
        this.setToken(token)
      }

      if (user) {
        this.setUser(user)
      } else if (this.token) {
        await this.fetchUser()
      }

      return this.user
    },
    async logout() {
      try {
        if (this.token) {
          await api.post('/api/logout')
        }
      } finally {
        this.clearAuth()
      }
    },
  },
})
