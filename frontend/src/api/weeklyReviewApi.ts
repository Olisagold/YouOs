import api from '@/lib/api'
import type { Paginated, WeeklyReviewRecord } from '@/types/api'

export const weeklyReviewApi = {
  async generate(): Promise<WeeklyReviewRecord> {
    const response = await api.post<WeeklyReviewRecord>('/api/v1/weekly-review/generate')
    return response.data
  },

  async list(): Promise<Paginated<WeeklyReviewRecord>> {
    const response = await api.get<Paginated<WeeklyReviewRecord>>('/api/v1/weekly-reviews')
    return response.data
  },
}

export default weeklyReviewApi
