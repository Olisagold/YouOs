<script setup>
import { computed } from 'vue'

const props = defineProps({
  id: {
    type: String,
    default: '',
  },
  modelValue: {
    type: [String, Number],
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
  type: {
    type: String,
    default: 'text',
  },
  placeholder: {
    type: String,
    default: '',
  },
  autocomplete: {
    type: String,
    default: 'off',
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

const fallbackId = `input-${Math.random().toString(36).slice(2, 10)}`

const inputId = computed(() => props.id || fallbackId)
const messageId = computed(() => `${inputId.value}-message`)

const inputClass = computed(() => {
  if (props.error) {
    return 'border-[var(--danger)] bg-[rgb(16,24,36)] text-[var(--text)] placeholder:text-[var(--muted)]'
  }

  return 'border-default bg-[rgb(16,24,36)] text-[var(--text)] placeholder:text-[var(--muted)] focus:border-[rgb(127,149,179,0.85)]'
})
</script>

<template>
  <div class="space-y-2">
    <label v-if="label" :for="inputId" class="block text-sm font-medium text-[var(--text)]">
      {{ label }}
      <span v-if="required" class="ml-1 text-[rgb(188,125,125)]">*</span>
    </label>

    <input
      :id="inputId"
      :value="modelValue"
      :type="type"
      :autocomplete="autocomplete"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :aria-invalid="error ? 'true' : 'false'"
      :aria-describedby="error || hint ? messageId : undefined"
      class="focus-ring w-full rounded-xl border px-4 py-2.5 text-sm transition duration-150 disabled:cursor-not-allowed disabled:opacity-60"
      :class="inputClass"
      @input="$emit('update:modelValue', $event.target.value)"
      @blur="$emit('blur', $event)"
    />

    <p v-if="error" :id="messageId" class="text-xs text-[var(--danger)]" role="alert">
      {{ error }}
    </p>
    <p v-else-if="hint" :id="messageId" class="text-xs text-[var(--muted)]">
      {{ hint }}
    </p>
  </div>
</template>
