import axios, { AxiosError, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth'

export type ApiErrorShape = {
  error: string
  message: string
  details?: unknown
  status?: number
}

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://127.0.0.1:8081',
  timeout: 30000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

const normalizeApiError = (error: AxiosError): ApiErrorShape => {
  const responseData = error.response?.data as
    | {
        error?: string
        message?: string
        details?: unknown
        errors?: unknown
      }
    | undefined

  return {
    error: responseData?.error ?? 'request_failed',
    message: responseData?.message ?? error.message ?? 'The request failed.',
    details: responseData?.details ?? responseData?.errors,
    status: error.response?.status,
  }
}

api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const authStore = useAuthStore()
  const token = authStore.token

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response: AxiosResponse) => response,
  (error: AxiosError) => Promise.reject(normalizeApiError(error)),
)

export { api, normalizeApiError }
export default api
