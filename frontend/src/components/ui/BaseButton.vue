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
      'border-[rgb(127,149,179,0.45)] bg-[linear-gradient(180deg,var(--accent-strong)_0%,var(--accent)_58%,var(--accent-deep)_100%)] text-[rgb(8,13,20)] shadow-[inset_0_1px_0_rgb(255,255,255,0.2),inset_0_-1px_0_rgb(8,13,20,0.3),0_12px_20px_rgb(4,8,14,0.3)] hover:brightness-[1.035]',
    secondary:
      'border-[rgb(181,162,121,0.32)] bg-[linear-gradient(180deg,rgb(181,162,121,0.24)_0%,rgb(181,162,121,0.12)_100%)] text-[rgb(226,212,185)] shadow-[inset_0_1px_0_rgb(255,255,255,0.12),0_10px_18px_rgb(6,10,16,0.25)] hover:brightness-[1.035]',
    ghost:
      'border-transparent bg-transparent text-[var(--muted)] hover:bg-white/5 hover:text-[var(--text)]',
    destructive:
      'border-[rgb(188,125,125,0.45)] bg-[linear-gradient(180deg,rgb(202,144,144)_0%,rgb(188,125,125)_100%)] text-[rgb(14,8,8)] shadow-[inset_0_1px_0_rgb(255,255,255,0.16),0_10px_18px_rgb(12,8,8,0.32)] hover:brightness-[1.035]',
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
    :aria-busy="loading ? 'true' : 'false'"
    class="focus-ring inline-flex items-center justify-center gap-2 rounded-xl border font-medium transition-all duration-150 enabled:hover:scale-[1.02] enabled:active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
    :class="[variantClass, sizeClass, block ? 'w-full' : '']"
  >
    <BaseSpinner v-if="loading" size="sm" />
    <slot />
  </button>
</template>
