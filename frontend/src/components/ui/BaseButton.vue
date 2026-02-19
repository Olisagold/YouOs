<script setup>
import { computed } from 'vue'
import BaseSpinner from './BaseSpinner.vue'

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'ghost', 'destructive'].includes(value),
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  type: {
    type: String,
    default: 'button',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  block: {
    type: Boolean,
    default: false,
  },
})

const variantClass = computed(() => {
  return {
    primary:
      'border-transparent bg-[rgb(127,149,179)] text-slate-950 hover:bg-[rgb(142,161,186)] active:bg-[rgb(117,138,168)]',
    secondary:
      'border-default bg-surface text-[var(--text)] hover:bg-elevated active:bg-[rgb(22,34,51)]',
    ghost:
      'border-transparent bg-transparent text-[var(--muted)] hover:bg-white/5 hover:text-[var(--text)]',
    destructive:
      'border-transparent bg-[rgb(188,125,125)] text-slate-950 hover:bg-[rgb(203,141,141)] active:bg-[rgb(172,108,108)]',
  }[props.variant]
})

const sizeClass = computed(() => {
  return {
    sm: 'h-9 px-3 text-xs',
    md: 'h-11 px-4 text-sm',
    lg: 'h-12 px-5 text-sm',
  }[props.size]
})

const isDisabled = computed(() => props.disabled || props.loading)
</script>

<template>
  <button
    :type="type"
    :disabled="isDisabled"
    class="focus-ring inline-flex items-center justify-center gap-2 rounded-xl border font-medium transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-55"
    :class="[variantClass, sizeClass, block ? 'w-full' : '']"
  >
    <BaseSpinner v-if="loading" size="sm" />
    <slot />
  </button>
</template>
