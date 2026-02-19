<script setup>
import { computed } from 'vue'

const props = defineProps({
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
})

const sizeClass = computed(() => {
  return {
    sm: 'h-4 w-4 border-2',
    md: 'h-5 w-5 border-2',
    lg: 'h-7 w-7 border-[3px]',
  }[props.size]
})
</script>

<template>
  <span
    class="inline-block animate-spin rounded-full border-current border-r-transparent align-middle"
    :class="sizeClass"
    aria-hidden="true"
  />
</template>
