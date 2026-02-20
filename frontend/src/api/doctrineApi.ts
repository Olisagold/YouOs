import api from '@/lib/api'
import type { DoctrinePayload, DoctrineRecord } from '@/types/api'

export const doctrineApi = {
  async get(): Promise<DoctrineRecord> {
    const response = await api.get<DoctrineRecord>('/api/v1/doctrine')
    return response.data
  },

  async upsert(payload: DoctrinePayload): Promise<DoctrineRecord> {
    const response = await api.put<DoctrineRecord>('/api/v1/doctrine', payload)
    return response.data
  },
}

export default doctrineApi
