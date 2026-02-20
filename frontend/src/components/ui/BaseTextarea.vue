<script setup>
import { computed } from 'vue'

const props = defineProps({
  id: {
    type: String,
    default: '',
  },
  modelValue: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  hint: {
    type: String,
    default: '',
  },
  error: {
    type: String,
    default: '',
  },
  rows: {
    type: Number,
    default: 4,
  },
  placeholder: {
    type: String,
    default: '',
  },
  required: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['update:modelValue', 'blur'])

const fallbackId = `textarea-${Math.random().toString(36).slice(2, 10)}`

const textareaId = computed(() => props.id || fallbackId)
const messageId = computed(() => `${textareaId.value}-message`)

const textareaClass = computed(() => {
  if (props.error) {
    return 'border-[rgb(188,125,125)] bg-[rgb(16,24,36)] text-[var(--text)] placeholder:text-[var(--muted)]'
  }

  return 'border-default bg-[rgb(16,24,36)] text-[var(--text)] placeholder:text-[var(--muted)]'
})
</script>

<template>
  <div class="space-y-2">
    <label v-if="label" :for="textareaId" class="block text-sm font-medium text-[var(--text)]">
      {{ label }}
      <span v-if="required" class="ml-1 text-[rgb(188,125,125)]">*</span>
    </label>

    <textarea
      :id="textareaId"
      :value="modelValue"
      :rows="rows"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :aria-invalid="error ? 'true' : 'false'"
      :aria-describedby="error || hint ? messageId : undefined"
      class="focus-ring w-full rounded-xl border px-4 py-2.5 text-sm transition duration-200 disabled:cursor-not-allowed disabled:opacity-60"
      :class="textareaClass"
      @input="$emit('update:modelValue', $event.target.value)"
      @blur="$emit('blur', $event)"
    />

    <p v-if="error" :id="messageId" class="text-xs text-[rgb(188,125,125)]" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint" :id="messageId" class="text-xs text-[var(--muted)]">
      {{ hint }}
    </p>
  </div>
</template>
