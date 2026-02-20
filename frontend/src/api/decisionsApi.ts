import api from '@/lib/api'
import type {
  DecisionCreatePayload,
  DecisionCreateResponse,
  DecisionRecord,
  DecisionCategory,
  Paginated,
} from '@/types/api'

export const decisionsApi = {
  async create(payload: DecisionCreatePayload): Promise<DecisionCreateResponse> {
    const response = await api.post<DecisionCreateResponse>('/api/v1/decisions', payload)
    return response.data
  },

  async list(category?: DecisionCategory): Promise<Paginated<DecisionRecord>> {
    const response = await api.get<Paginated<DecisionRecord>>('/api/v1/decisions', {
      params: category ? { category } : {},
    })
    return response.data
  },

  async getById(id: number | string): Promise<DecisionRecord> {
    const response = await api.get<DecisionRecord>(`/api/v1/decisions/${id}`)
    return response.data
  },
}

export default decisionsApi
