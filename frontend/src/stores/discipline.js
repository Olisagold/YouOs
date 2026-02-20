import { defineStore } from 'pinia'
import disciplineApi from '@/api/disciplineApi'
import waitForAuthInitialization from '@/lib/waitForAuthInitialization'

const STREAK_TTL_MS = 60_000

export const useDisciplineStore = defineStore('discipline', {
  state: () => ({
    streak: null,
    isLoaded: false,
    isLoading: false,
    lastFetchAt: 0,
  }),
  actions: {
    async loadStreak(options = {}) {
      await waitForAuthInitialization()

      const force = options.force === true
      const cacheFresh = Date.now() - this.lastFetchAt < STREAK_TTL_MS

      if (this.isLoading) {
        return this.streak
      }

      if (this.isLoaded && !force && cacheFresh) {
        return this.streak
      }

      this.isLoading = true

      try {
        const streak = await disciplineApi.getStreak()
        this.streak = streak
        this.isLoaded = true
        this.lastFetchAt = Date.now()
        return streak
      } finally {
        this.isLoading = false
      }
    },
  },
})
