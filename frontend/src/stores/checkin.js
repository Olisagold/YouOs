import { defineStore } from 'pinia'
import checkinApi from '@/api/checkinApi'
import waitForAuthInitialization from '@/lib/waitForAuthInitialization'

const todayKey = () => new Date().toISOString().slice(0, 10)

export const useCheckinStore = defineStore('checkin', {
  state: () => ({
    todayCheckin: null,
    isLoaded: false,
    isLoading: false,
    isCreating: false,
    loadedDateKey: '',
  }),
  actions: {
    async loadToday(options = {}) {
      await waitForAuthInitialization()

      const force = options.force === true
      const currentKey = todayKey()

      if (this.isLoading) {
        return this.todayCheckin
      }

      if (this.isLoaded && !force && this.loadedDateKey === currentKey) {
        return this.todayCheckin
      }

      this.isLoading = true

      try {
        const checkin = await checkinApi.getToday()
        this.todayCheckin = checkin
        this.isLoaded = true
        this.loadedDateKey = currentKey
        return checkin
      } catch (error) {
        if (error?.status === 404) {
          this.todayCheckin = null
          this.isLoaded = true
          this.loadedDateKey = currentKey
          return null
        }

        throw error
      } finally {
        this.isLoading = false
      }
    },

    async create(payload) {
      await waitForAuthInitialization()

      if (this.isCreating) {
        return this.todayCheckin
      }

      this.isCreating = true

      try {
        const checkin = await checkinApi.create(payload)
        this.todayCheckin = checkin
        this.isLoaded = true
        this.loadedDateKey = todayKey()
        return checkin
      } finally {
        this.isCreating = false
      }
    },
  },
})
