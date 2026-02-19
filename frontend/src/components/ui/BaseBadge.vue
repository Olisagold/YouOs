<script setup>
import { computed } from 'vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'neutral',
    validator: (value) => ['neutral', 'accent', 'success', 'warning', 'danger'].includes(value),
  },
})

const variantClass = computed(() => {
  return {
    neutral: 'border-default bg-white/5 text-[var(--muted)]',
    accent: 'border-[rgb(127,149,179,0.4)] bg-[rgb(127,149,179,0.18)] text-[rgb(176,195,222)]',
    success: 'border-[rgb(126,165,147,0.4)] bg-[rgb(126,165,147,0.18)] text-[rgb(181,215,197)]',
    warning: 'border-[rgb(181,158,115,0.4)] bg-[rgb(181,158,115,0.18)] text-[rgb(225,208,174)]',
    danger: 'border-[rgb(188,125,125,0.4)] bg-[rgb(188,125,125,0.18)] text-[rgb(229,189,189)]',
  }[props.variant]
})
</script>

<template>
  <span
    class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-medium uppercase tracking-[0.04em]"
    :class="variantClass"
  >
    <slot />
  </span>
</template>
