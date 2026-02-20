import { pinia } from '@/stores'
import { useAuthStore } from '@/stores/auth'

export const waitForAuthInitialization = async () => {
  const authStore = useAuthStore(pinia)

  if (!authStore.initialized) {
    await authStore.initialize()
  }

  return authStore
}

export default waitForAuthInitialization
