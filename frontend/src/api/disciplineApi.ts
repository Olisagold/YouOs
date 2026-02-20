import api from '@/lib/api'
import type { DisciplineStreak } from '@/types/api'

export const disciplineApi = {
  async getStreak(): Promise<DisciplineStreak> {
    const response = await api.get<DisciplineStreak>('/api/v1/discipline-log/streak')
    return response.data
  },
}

export default disciplineApi
