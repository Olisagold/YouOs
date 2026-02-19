<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  CalendarRange,
  ClipboardCheck,
  Gauge,
  LogOut,
  Menu,
  Scale,
  Settings2,
  History,
  X,
} from 'lucide-vue-next'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const authStore = useAuthStore()
const toastStore = useToastStore()
const route = useRoute()
const router = useRouter()
const mobileMenuOpen = ref(false)

const navItems = [
  { to: '/dashboard', label: 'Command Center', icon: Gauge },
  { to: '/checkin', label: 'Daily Check-In', icon: ClipboardCheck },
  { to: '/decision', label: 'Ask Decision', icon: Scale },
  { to: '/decisions', label: 'Decision History', icon: History },
  { to: '/weekly-review', label: 'Weekly Review', icon: CalendarRange },
  { to: '/settings/doctrine', label: 'Doctrine Settings', icon: Settings2 },
]

const pageTitle = computed(() => route.meta.title ?? 'Stoic OS')

const closeMobileMenu = () => {
  mobileMenuOpen.value = false
}

const handleLogout = async () => {
  await authStore.logout()
  toastStore.success('Signed out successfully.', 'Logged out')
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen lg:grid lg:grid-cols-[272px_1fr]">
    <div
      v-if="mobileMenuOpen"
      class="fixed inset-0 z-30 bg-slate-950/65 backdrop-blur-sm lg:hidden"
      @click="closeMobileMenu"
    />

    <aside
      class="stoic-panel fixed inset-y-0 left-0 z-40 w-[272px] border-r transition-transform duration-300 lg:static lg:translate-x-0"
      :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex h-full flex-col px-4 pb-6 pt-5">
        <div class="mb-6 flex items-center justify-between">
          <div class="space-y-1">
            <p class="text-xs uppercase tracking-[0.16em] text-accent">Stoic OS</p>
            <p class="text-sm text-muted">Discipline Engine</p>
          </div>
          <button class="focus-ring rounded-xl p-2 text-muted lg:hidden" type="button" @click="closeMobileMenu">
            <X class="h-5 w-5" />
          </button>
        </div>

        <nav class="space-y-2">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="focus-ring flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm transition-all duration-200"
            :class="
              route.path === item.to
                ? 'border-[rgb(127,149,179,0.5)] bg-[rgb(127,149,179,0.16)] text-[var(--text)]'
                : 'border-transparent text-muted hover:border-default hover:bg-white/5 hover:text-[var(--text)]'
            "
            @click="closeMobileMenu"
          >
            <component :is="item.icon" class="h-4 w-4" />
            <span>{{ item.label }}</span>
          </RouterLink>
        </nav>

        <div class="mt-auto rounded-2xl border border-default bg-white/5 p-4">
          <p class="text-xs uppercase tracking-[0.14em] text-muted">Session</p>
          <p class="mt-2 text-sm font-medium text-[var(--text)]">System steady</p>
          <p class="mt-1 text-xs text-muted">No active anomalies detected.</p>
        </div>
      </div>
    </aside>

    <div class="flex min-h-screen flex-col">
      <header class="sticky top-0 z-20 border-b border-default bg-[rgb(8,13,20,0.78)] backdrop-blur-xl">
        <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-3 md:px-8">
          <div class="flex items-center gap-3">
            <button
              class="focus-ring rounded-xl border border-default p-2 text-muted lg:hidden"
              type="button"
              @click="mobileMenuOpen = true"
            >
              <Menu class="h-5 w-5" />
            </button>
            <div>
              <h1 class="text-base font-semibold text-[var(--text)] md:text-lg">{{ pageTitle }}</h1>
              <p class="text-xs text-muted">Objective execution mode</p>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <BaseBadge variant="accent">Status: stable</BaseBadge>
            <BaseButton size="sm" variant="ghost" @click="handleLogout">
              <LogOut class="h-4 w-4" />
              Logout
            </BaseButton>
          </div>
        </div>
      </header>

      <main class="flex-1 px-4 pb-8 pt-6 md:px-8">
        <div class="mx-auto w-full max-w-6xl">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>
