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
    listPage: 1,
    listPerPage: 10,
    lastListKey: '',
    lastListFetchAt: 0,
    pagination: null,
  }),
  actions: {
    async createDecision(payload) {
      await waitForAuthInitialization({ requireAuth: true })

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
      await waitForAuthInitialization({ requireAuth: true })

      const force = options.force === true
      const category = options.category ?? null
      const page = Number(options.page ?? 1)
      const perPage = Number(options.perPage ?? 10)
      const safePage = Number.isFinite(page) && page > 0 ? page : 1
      const safePerPage = Number.isFinite(perPage) && perPage > 0 ? perPage : 10
      const listKey = `${category ?? 'all'}:${safePage}:${safePerPage}`
      const cacheFresh = Date.now() - this.lastListFetchAt < LIST_TTL_MS

      if (this.isListing) {
        return this.decisions
      }

      if (this.isListLoaded && !force && this.lastListKey === listKey && cacheFresh) {
        return this.decisions
      }

      this.isListing = true

      try {
        const pageData = await decisionsApi.list(category ?? undefined, {
          page: safePage,
          perPage: safePerPage,
        })
        this.decisions = pageData.data
        this.pagination = pageData
        this.isListLoaded = true
        this.listCategory = category
        this.listPage = safePage
        this.listPerPage = safePerPage
        this.lastListKey = listKey
        this.lastListFetchAt = Date.now()
        if (safePage === 1 && category == null) {
          this.lastDecision = pageData.data[0] ?? this.lastDecision
        }

        return this.decisions
      } finally {
        this.isListing = false
      }
    },
  },
})

