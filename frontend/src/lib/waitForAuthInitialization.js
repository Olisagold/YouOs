import { pinia } from '@/stores'
import { useAuthStore } from '@/stores/auth'
import { buildSignInRequiredError } from '@/lib/authErrors'

export const waitForAuthInitialization = async (options = {}) => {
  const requireAuth = options.requireAuth === true
  const authStore = useAuthStore(pinia)

  if (!authStore.initialized) {
    await authStore.initialize()
  }

  if (requireAuth && !authStore.isAuthenticated) {
    throw buildSignInRequiredError()
  }

  return authStore
}

export default waitForAuthInitialization
