<script setup>
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const authStore = useAuthStore()
const toastStore = useToastStore()
const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const errors = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const isSubmitting = ref(false)

const validateEmail = (value) => /\S+@\S+\.\S+/.test(value)

const clearErrors = () => {
  errors.name = ''
  errors.email = ''
  errors.password = ''
  errors.password_confirmation = ''
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

  if (!form.name.trim()) {
    errors.name = 'Name is required.'
  } else if (form.name.trim().length < 2) {
    errors.name = 'Name must be at least 2 characters.'
  }

  if (!form.email.trim()) {
    errors.email = 'Email is required.'
  } else if (!validateEmail(form.email.trim())) {
    errors.email = 'Enter a valid email address.'
  }

  if (!form.password) {
    errors.password = 'Password is required.'
  } else if (form.password.length < 8) {
    errors.password = 'Password must be at least 8 characters.'
  }

  if (!form.password_confirmation) {
    errors.password_confirmation = 'Confirm your password.'
  } else if (form.password !== form.password_confirmation) {
    errors.password_confirmation = 'Password confirmation does not match.'
  }

  return !errors.name && !errors.email && !errors.password && !errors.password_confirmation
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
    await authStore.register({
      name: form.name.trim(),
      email: form.email.trim(),
      password: form.password,
      password_confirmation: form.password_confirmation,
    })

    toastStore.success('Account created successfully.', 'Registration complete')

    if (authStore.isAuthenticated) {
      router.push('/dashboard')
    } else {
      router.push('/login')
    }
  } catch (error) {
    applyServerErrors(error.details)
    toastStore.error(error.message ?? 'Unable to create account.')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="mx-auto w-full max-w-md space-y-8">
    <div class="space-y-3 text-center">
      <p class="text-[11px] uppercase tracking-[0.24em] text-accent">STOIC OS</p>
      <h1 class="text-3xl font-bold text-[var(--text)]">Create your account</h1>
      <p class="text-sm leading-relaxed text-muted">
        Set up your workspace and start objective execution.
      </p>
    </div>

    <form class="space-y-5" @submit.prevent="submit">
      <BaseInput
        v-model="form.name"
        label="Name"
        autocomplete="name"
        placeholder="Your name"
        :error="errors.name"
        required
      />

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
        autocomplete="new-password"
        placeholder="At least 8 characters"
        :error="errors.password"
        required
      />

      <BaseInput
        v-model="form.password_confirmation"
        type="password"
        label="Confirm password"
        autocomplete="new-password"
        placeholder="Repeat your password"
        :error="errors.password_confirmation"
        required
      />

      <BaseButton type="submit" :loading="isSubmitting" :disabled="isSubmitting" block>
        Create account
      </BaseButton>
    </form>

    <p class="pt-1 text-center text-sm text-muted">
      Already registered?
      <RouterLink class="ml-1 text-accent hover:text-[var(--accent-strong)]" to="/login">
        Sign in
      </RouterLink>
    </p>
  </div>
</template>
