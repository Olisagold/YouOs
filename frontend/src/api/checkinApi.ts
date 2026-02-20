import api from '@/lib/api'
import type { DailyCheckinPayload, DailyCheckinRecord } from '@/types/api'

export const checkinApi = {
  async getToday(): Promise<DailyCheckinRecord> {
    const response = await api.get<DailyCheckinRecord>('/api/v1/checkin/today')
    return response.data
  },

  async create(payload: DailyCheckinPayload): Promise<DailyCheckinRecord> {
    const response = await api.post<DailyCheckinRecord>('/api/v1/checkin', payload)
    return response.data
  },
}

export default checkinApi
