<script setup>
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppShell from '@/layouts/AppShell.vue'
import ToastViewport from '@/components/ui/ToastViewport.vue'

const route = useRoute()
const isAuthLayout = computed(() => route.meta.layout === 'auth')
</script>

<template>
  <div class="min-h-screen">
    <div
      v-if="isAuthLayout"
      class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-12 md:px-8"
    >
      <div class="stoic-panel auth-card-enter relative z-10 w-full max-w-md rounded-3xl p-8 md:p-10">
        <RouterView v-slot="{ Component }">
          <Transition name="route-fade" mode="out-in">
            <component :is="Component" />
          </Transition>
        </RouterView>
      </div>
    </div>

    <AppShell v-else>
      <RouterView v-slot="{ Component }">
        <Transition name="route-fade" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </AppShell>

    <ToastViewport />
  </div>
</template>

<style scoped>
.route-fade-enter-active,
.route-fade-leave-active {
  transition: all 220ms ease;
}

.route-fade-enter-from,
.route-fade-leave-to {
  opacity: 0;
  transform: translateY(6px);
}
</style>
