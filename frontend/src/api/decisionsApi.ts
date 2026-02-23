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

  async list(
    category?: DecisionCategory,
    options: { page?: number; perPage?: number } = {},
  ): Promise<Paginated<DecisionRecord>> {
    const params: Record<string, unknown> = {}

    if (category) {
      params.category = category
    }

    if (Number.isFinite(options.page) && Number(options.page) > 0) {
      params.page = Number(options.page)
    }

    if (Number.isFinite(options.perPage) && Number(options.perPage) > 0) {
      params.per_page = Number(options.perPage)
    }

    const response = await api.get<Paginated<DecisionRecord>>('/api/v1/decisions', {
      params,
    })
    return response.data
  },

  async getById(id: number | string): Promise<DecisionRecord> {
    const response = await api.get<DecisionRecord>(`/api/v1/decisions/${id}`)
    return response.data
  },
}

export default decisionsApi
