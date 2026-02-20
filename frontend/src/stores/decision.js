import { defineStore } from 'pinia'
import decisionsApi from '@/api/decisionsApi'
import waitForAuthInitialization from '@/lib/waitForAuthInitialization'

const LIST_TTL_MS = 60_000

export const useDecisionStore = defineStore('decision', {
  state: () => ({
    decisions: [],
    lastDecision: null,
    isListLoaded: false,
    isListing: false,
    isCreating: false,
    listCategory: null,
    lastListFetchAt: 0,
    pagination: null,
  }),
  actions: {
    async createDecision(payload) {
      await waitForAuthInitialization()

      if (this.isCreating) {
        return null
      }

      this.isCreating = true

      try {
        const response = await decisionsApi.create(payload)
        const created = response.decision

        if (created) {
          this.lastDecision = created
          this.decisions = [created, ...this.decisions.filter((item) => item.id !== created.id)]
          this.isListLoaded = true
        }

        return response
      } finally {
        this.isCreating = false
      }
    },

    async listDecisions(options = {}) {
      await waitForAuthInitialization()

      const force = options.force === true
      const category = options.category ?? null
      const cacheFresh = Date.now() - this.lastListFetchAt < LIST_TTL_MS

      if (this.isListing) {
        return this.decisions
      }

      if (this.isListLoaded && !force && this.listCategory === category && cacheFresh) {
        return this.decisions
      }

      this.isListing = true

      try {
        const page = await decisionsApi.list(category ?? undefined)
        this.decisions = page.data
        this.pagination = page
        this.isListLoaded = true
        this.listCategory = category
        this.lastListFetchAt = Date.now()
        this.lastDecision = page.data[0] ?? this.lastDecision
        return this.decisions
      } finally {
        this.isListing = false
      }
    },
  },
})
