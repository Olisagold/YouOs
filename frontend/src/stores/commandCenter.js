import { defineStore } from 'pinia'
import checkinApi from '@/api/checkinApi'
import disciplineApi from '@/api/disciplineApi'
import doctrineApi from '@/api/doctrineApi'
import { isSignInRequiredError } from '@/lib/authErrors'
import waitForAuthInitialization from '@/lib/waitForAuthInitialization'

const STATUS_CACHE_TTL_MS = 60_000
let prefetchRequest = null

export const useCommandCenterStore = defineStore('commandCenter', {
  state: () => ({
    doctrine: null,
    doctrineMissing: false,
    todayCheckin: null,
    streak: null,
    isLoaded: false,
    isLoading: false,
    authRequired: false,
    errorMessage: '',
    lastFetchedAt: 0,
  }),
  getters: {
    doctrineConfigured: (state) => Boolean(state.doctrine),
    checkinCompleted: (state) => Boolean(state.todayCheckin),
    cacheFresh: (state) => Date.now() - state.lastFetchedAt < STATUS_CACHE_TTL_MS,
  },
  actions: {
    clearStatus() {
      this.doctrine = null
      this.doctrineMissing = false
      this.todayCheckin = null
      this.streak = null
      this.isLoaded = false
      this.isLoading = false
      this.authRequired = false
      this.errorMessage = ''
      this.lastFetchedAt = 0
    },

    async prefetchStatus(options = {}) {
      const force = options.force === true
      const authStore = await waitForAuthInitialization()

      if (!authStore.isAuthenticated) {
        this.clearStatus()
        this.authRequired = true
        return null
      }

      if (this.isLoading && prefetchRequest) {
        return prefetchRequest
      }

      if (this.isLoaded && !force && this.cacheFresh) {
        return {
          doctrine: this.doctrine,
          todayCheckin: this.todayCheckin,
          streak: this.streak,
        }
      }

      this.isLoading = true
      this.authRequired = false
      this.errorMessage = ''

      prefetchRequest = (async () => {
        const [doctrineResult, checkinResult, streak] = await Promise.all([
          doctrineApi
            .get()
            .then((value) => ({ type: 'present', value }))
            .catch((error) => {
              if (error?.status === 404) {
                return { type: 'missing', value: null }
              }

              throw error
            }),
          checkinApi
            .getToday()
            .then((value) => ({ type: 'present', value }))
            .catch((error) => {
              if (error?.status === 404) {
                return { type: 'missing', value: null }
              }

              throw error
            }),
          disciplineApi.getStreak(),
        ])

        this.doctrine = doctrineResult.value
        this.doctrineMissing = doctrineResult.type === 'missing'
        this.todayCheckin = checkinResult.value
        this.streak = streak
        this.isLoaded = true
        this.lastFetchedAt = Date.now()

        return {
          doctrine: this.doctrine,
          todayCheckin: this.todayCheckin,
          streak: this.streak,
        }
      })()

      try {
        return await prefetchRequest
      } catch (error) {
        if (isSignInRequiredError(error)) {
          this.clearStatus()
          this.authRequired = true
        }

        this.errorMessage = error?.message ?? 'Unable to prefetch dashboard data.'
        throw error
      } finally {
        this.isLoading = false
        prefetchRequest = null
      }
    },
  },
})

export default useCommandCenterStore
