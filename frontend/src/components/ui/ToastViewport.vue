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
  success: 'border-[rgb(126,165,147,0.35)]',
  warning: 'border-[rgb(181,158,115,0.35)]',
  danger: 'border-[rgb(188,125,125,0.35)]',
  info: 'border-[rgb(127,149,179,0.35)]',
}

const iconStyleByType = {
  success: 'bg-[rgb(126,165,147,0.22)] text-[rgb(177,211,193)]',
  warning: 'bg-[rgb(181,158,115,0.22)] text-[rgb(224,207,170)]',
  danger: 'bg-[rgb(188,125,125,0.22)] text-[rgb(230,194,194)]',
  info: 'bg-[rgb(127,149,179,0.22)] text-[rgb(196,212,236)]',
}

const toasts = computed(() => toastStore.toasts)

const iconFor = (type) => iconByType[type] ?? Info
const styleFor = (type) => styleByType[type] ?? styleByType.info
const iconStyleFor = (type) => iconStyleByType[type] ?? iconStyleByType.info
</script>

<template>
  <div
    class="pointer-events-none fixed right-4 top-4 z-[80] flex w-[min(360px,calc(100vw-2rem))] flex-col gap-3"
    aria-live="polite"
    aria-atomic="true"
  >
    <TransitionGroup name="toast-slide" tag="div" class="flex flex-col gap-3">
      <article
        v-for="toast in toasts"
        :key="toast.id"
        class="stoic-panel pointer-events-auto rounded-2xl border bg-[rgb(16,24,36,0.94)] p-4 text-[var(--text)] shadow-soft backdrop-blur-sm"
        :class="styleFor(toast.type)"
      >
        <div class="flex items-start gap-3">
          <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg" :class="iconStyleFor(toast.type)">
            <component :is="iconFor(toast.type)" class="h-4 w-4" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold">{{ toast.title }}</p>
            <p v-if="toast.message" class="mt-1 text-sm text-[var(--muted)]">{{ toast.message }}</p>
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
  transform: translateY(-12px) translateX(10px);
}
</style>
