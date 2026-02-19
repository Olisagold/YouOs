import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { pinia } from '@/stores'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginPage.vue'),
      meta: {
        title: 'Login',
        guestOnly: true,
        layout: 'auth',
      },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterPage.vue'),
      meta: {
        title: 'Register',
        guestOnly: true,
        layout: 'auth',
      },
    },
    {
      path: '/',
      redirect: '/dashboard',
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('@/views/CommandCenter.vue'),
      meta: {
        title: 'Command Center',
        requiresAuth: true,
      },
    },
    {
      path: '/checkin',
      name: 'checkin',
      component: () => import('@/views/DailyCheckin.vue'),
      meta: {
        title: 'Daily Check-In',
        requiresAuth: true,
      },
    },
    {
      path: '/decision',
      name: 'decision',
      component: () => import('@/views/AskDecision.vue'),
      meta: {
        title: 'Ask Decision',
        requiresAuth: true,
      },
    },
    {
      path: '/decisions',
      name: 'decisions',
      component: () => import('@/views/DecisionHistory.vue'),
      meta: {
        title: 'Decision History',
        requiresAuth: true,
      },
    },
    {
      path: '/weekly-review',
      name: 'weekly-review',
      component: () => import('@/views/WeeklyReview.vue'),
      meta: {
        title: 'Weekly Review',
        requiresAuth: true,
      },
    },
    {
      path: '/settings/doctrine',
      name: 'doctrine-settings',
      component: () => import('@/views/DoctrineSettings.vue'),
      meta: {
        title: 'Doctrine Settings',
        requiresAuth: true,
      },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/dashboard',
    },
  ],
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore(pinia)

  if (!authStore.initialized && !authStore.initializing) {
    await authStore.initialize()
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return {
      path: '/login',
      query: to.fullPath !== '/dashboard' ? { redirect: to.fullPath } : {},
    }
  }

  if (to.meta.guestOnly && authStore.isAuthenticated) {
    return { path: '/dashboard' }
  }

  return true
})

export default router
