import { defineStore } from 'pinia'
import doctrineApi from '@/api/doctrineApi'

export const useDoctrineStore = defineStore('doctrine', {
  state: () => ({
    doctrine: null,
    isLoaded: false,
    isLoading: false,
    isSaving: false,
  }),
  actions: {
    async load(options = {}) {
      const force = options.force === true

      if (this.isLoading) {
        return this.doctrine
      }

      if (this.isLoaded && !force) {
        return this.doctrine
      }

      this.isLoading = true

      try {
        const doctrine = await doctrineApi.get()
        this.doctrine = doctrine
        this.isLoaded = true
        return doctrine
      } catch (error) {
        if (error?.status === 404) {
          this.doctrine = null
          this.isLoaded = true
          return null
        }

        throw error
      } finally {
        this.isLoading = false
      }
    },

    async save(payload) {
      this.isSaving = true

      try {
        const doctrine = await doctrineApi.upsert(payload)
        this.doctrine = doctrine
        this.isLoaded = true
        return doctrine
      } finally {
        this.isSaving = false
      }
    },
  },
})
