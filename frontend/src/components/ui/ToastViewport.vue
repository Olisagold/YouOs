<script setup>
import { computed } from 'vue'
import { AlertTriangle, CheckCircle2, Info, OctagonX, X } from 'lucide-vue-next'
import { useToastStore } from '@/stores/toast'

const toastStore = useToastStore()

const iconByType = {
  success: CheckCircle2,
  warning: AlertTriangle,
  danger: OctagonX,
  info: Info,
}

const styleByType = {
  success: 'border-[rgb(126,165,147,0.45)] bg-[rgb(126,165,147,0.16)] text-[rgb(198,225,212)]',
  warning: 'border-[rgb(181,158,115,0.45)] bg-[rgb(181,158,115,0.16)] text-[rgb(236,220,190)]',
  danger: 'border-[rgb(188,125,125,0.45)] bg-[rgb(188,125,125,0.16)] text-[rgb(240,208,208)]',
  info: 'border-[rgb(127,149,179,0.45)] bg-[rgb(127,149,179,0.16)] text-[rgb(210,223,241)]',
}

const toasts = computed(() => toastStore.toasts)

const iconFor = (type) => iconByType[type] ?? Info
const styleFor = (type) => styleByType[type] ?? styleByType.info
</script>

<template>
  <div class="pointer-events-none fixed right-4 top-4 z-[80] flex w-[min(360px,calc(100vw-2rem))] flex-col gap-3">
    <TransitionGroup name="toast-slide" tag="div" class="flex flex-col gap-3">
      <article
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto rounded-2xl border p-4 shadow-soft backdrop-blur-sm"
        :class="styleFor(toast.type)"
      >
        <div class="flex items-start gap-3">
          <component :is="iconFor(toast.type)" class="mt-0.5 h-4 w-4 shrink-0" />
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold">{{ toast.title }}</p>
            <p v-if="toast.message" class="mt-1 text-sm opacity-90">{{ toast.message }}</p>
          </div>
          <button
            class="focus-ring rounded-lg p-1 text-current/80 transition hover:bg-black/10 hover:text-current"
            type="button"
            @click="toastStore.remove(toast.id)"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
      </article>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 220ms ease;
}

.toast-slide-enter-from,
.toast-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px) translateX(8px);
}
</style>
