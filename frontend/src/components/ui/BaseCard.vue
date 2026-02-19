<script setup>
const props = defineProps({
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
  elevated: {
    type: Boolean,
    default: false,
  },
  padded: {
    type: Boolean,
    default: true,
  },
})
</script>

<template>
  <section
    class="rounded-2xl border border-default bg-surface"
    :class="[elevated ? 'shadow-soft bg-elevated' : '', padded ? 'p-5 md:p-6' : '']"
  >
    <header v-if="title || subtitle || $slots.header" class="mb-5">
      <slot name="header">
        <h3 v-if="title" class="text-base font-semibold text-[var(--text)]">{{ title }}</h3>
        <p v-if="subtitle" class="mt-1 text-sm text-[var(--muted)]">{{ subtitle }}</p>
      </slot>
    </header>

    <div>
      <slot />
    </div>
  </section>
</template>
