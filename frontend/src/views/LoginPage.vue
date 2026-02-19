<script setup>
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const authStore = useAuthStore()
const toastStore = useToastStore()
const route = useRoute()
const router = useRouter()

const form = reactive({
  email: '',
  password: '',
})

const errors = reactive({
  email: '',
  password: '',
})

const isSubmitting = ref(false)
const redirectPath = computed(() =>
  typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard',
)

const validateEmail = (value) => /\S+@\S+\.\S+/.test(value)

const clearErrors = () => {
  errors.email = ''
  errors.password = ''
}

const applyServerErrors = (details) => {
  if (!details || typeof details !== 'object') {
    return
  }

  for (const key of Object.keys(errors)) {
    const value = details[key]
    if (Array.isArray(value) && value.length) {
      errors[key] = String(value[0])
    }
  }
}

const validate = () => {
  clearErrors()

  if (!form.email.trim()) {
    errors.email = 'Email is required.'
  } else if (!validateEmail(form.email.trim())) {
    errors.email = 'Enter a valid email address.'
  }

  if (!form.password) {
    errors.password = 'Password is required.'
  } else if (form.password.length < 6) {
    errors.password = 'Password must be at least 6 characters.'
  }

  return !errors.email && !errors.password
}

const submit = async () => {
  if (isSubmitting.value) {
    return
  }

  if (!validate()) {
    return
  }

  isSubmitting.value = true

  try {
    await authStore.login({
      email: form.email.trim(),
      password: form.password,
    })

    toastStore.success('Authentication successful.', 'Welcome back')
    router.push(redirectPath.value)
  } catch (error) {
    applyServerErrors(error.details)
    toastStore.error(error.message ?? 'Unable to sign in with provided credentials.')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto w-full max-w-md space-y-8">
    <div class="space-y-3 text-center">
      <p class="text-[11px] uppercase tracking-[0.24em] text-accent">STOIC OS</p>
      <h1 class="text-3xl font-bold text-[var(--text)]">Welcome back</h1>
      <p class="text-sm leading-relaxed text-muted">
        Sign in to continue your disciplined command center.
      </p>
    </div>

    <form class="space-y-5" @submit.prevent="submit">
      <BaseInput
        v-model="form.email"
        label="Email"
        autocomplete="email"
        placeholder="you@example.com"
        :error="errors.email"
        required
      />

      <BaseInput
        v-model="form.password"
        type="password"
        label="Password"
        autocomplete="current-password"
        placeholder="Your password"
        :error="errors.password"
        required
      />

      <BaseButton type="submit" :loading="isSubmitting" :disabled="isSubmitting" block>
        Sign in
      </BaseButton>
    </form>

    <p class="pt-1 text-center text-sm text-muted">
      New here?
      <RouterLink class="ml-1 text-accent hover:text-[var(--accent-strong)]" to="/register">
        Create an account
      </RouterLink>
    </p>
  </div>
</template>
