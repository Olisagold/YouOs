import { defineStore } from 'pinia'
import weeklyReviewApi from '@/api/weeklyReviewApi'
import waitForAuthInitialization from '@/lib/waitForAuthInitialization'

const LIST_TTL_MS = 60_000

export const useWeeklyReviewStore = defineStore('weeklyReview', {
  state: () => ({
    reviews: [],
    latestReview: null,
    isLoaded: false,
    isListing: false,
    isGenerating: false,
    lastListFetchAt: 0,
    pagination: null,
  }),
  actions: {
    async list(options = {}) {
      await waitForAuthInitialization()

      const force = options.force === true
      const cacheFresh = Date.now() - this.lastListFetchAt < LIST_TTL_MS

      if (this.isListing) {
        return this.reviews
      }

      if (this.isLoaded && !force && cacheFresh) {
        return this.reviews
      }

      this.isListing = true

      try {
        const page = await weeklyReviewApi.list()
        this.reviews = page.data
        this.latestReview = page.data[0] ?? null
        this.pagination = page
        this.lastListFetchAt = Date.now()
        this.isLoaded = true
        return this.reviews
      } finally {
        this.isListing = false
      }
    },

    async generate() {
      await waitForAuthInitialization()

      if (this.isGenerating) {
        return this.latestReview
      }

      this.isGenerating = true

      try {
        const review = await weeklyReviewApi.generate()
        this.latestReview = review
        this.reviews = [review, ...this.reviews.filter((item) => item.id !== review.id)]
        this.isLoaded = true
        this.lastListFetchAt = Date.now()
        return review
      } finally {
        this.isGenerating = false
      }
    },
  },
})
