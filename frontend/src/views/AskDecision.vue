<script setup>
import { computed, reactive, ref } from 'vue'
import { Plus, RefreshCw, Trash2 } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import VerdictCard from '@/components/decision/VerdictCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import { useDecisionStore } from '@/stores/decision'
import { useToastStore } from '@/stores/toast'

const router = useRouter()
const decisionStore = useDecisionStore()
const toastStore = useToastStore()

const categories = ['financial', 'health', 'work', 'social', 'mindset', 'other']
const urgencies = ['low', 'medium', 'high']

const form = reactive({
  category: 'work',
  what: '',
  why: '',
  when: '',
  urgency: 'medium',
  estimatedImpact: '',
  alternatives: [''],
})

const errors = reactive({
  category: '',
  what: '',
  why: '',
  when: '',
  urgency: '',
  estimatedImpact: '',
  alternatives: [],
  general: '',
})

const submitError = reactive({
  code: '',
  message: '',
})

const aiResponse = ref(null)
const isSubmitting = computed(() => decisionStore.isCreating)

const clearErrors = () => {
  errors.category = ''
  errors.what = ''
  errors.why = ''
  errors.when = ''
  errors.urgency = ''
  errors.estimatedImpact = ''
  errors.alternatives = []
  errors.general = ''
}

const clearSubmitError = () => {
  submitError.code = ''
  submitError.message = ''
}

const validate = () => {
  clearErrors()

  if (!categories.includes(form.category)) {
    errors.category = 'Select a valid category.'
  }

  if (!form.what.trim()) {
    errors.what = 'This field is required.'
  }

  if (!form.why.trim()) {
    errors.why = 'This field is required.'
  }

  if (!form.when.trim()) {
    errors.when = 'This field is required.'
  }

  if (!urgencies.includes(form.urgency)) {
    errors.urgency = 'Select urgency.'
  }

  if (!form.estimatedImpact.trim()) {
    errors.estimatedImpact = 'This field is required.'
  }

  errors.alternatives = form.alternatives.map((item) =>
    item.trim() ? '' : 'Alternative cannot be empty.',
  )

  const hasAlternativeErrors = errors.alternatives.some(Boolean)

  return (
    !errors.category &&
    !errors.what &&
    !errors.why &&
    !errors.when &&
    !errors.urgency &&
    !errors.estimatedImpact &&
    !hasAlternativeErrors
  )
}

const addAlternative = () => {
  if (form.alternatives.length >= 6) {
    return
  }

  form.alternatives.push('')
}

const removeAlternative = (index) => {
  if (form.alternatives.length <= 1) {
    return
  }

  form.alternatives.splice(index, 1)
}

const applySubmitError = (error) => {
  submitError.code = error?.error ?? 'request_failed'
  submitError.message = error?.message ?? 'Unable to process this decision.'
}

const retry = async () => {
  await submit()
}

const submit = async () => {
  if (isSubmitting.value) {
    return
  }

  clearSubmitError()

  if (!validate()) {
    toastStore.warning('Fix decision fields before submitting.')
    return
  }

  try {
    const response = await decisionStore.createDecision({
      category: form.category,
      context_json: {
        what: form.what.trim(),
        why: form.why.trim(),
        when: form.when.trim(),
        urgency: form.urgency,
        estimated_impact: form.estimatedImpact.trim(),
        alternatives: form.alternatives.map((item) => item.trim()).filter(Boolean),
      },
    })

    aiResponse.value = response?.ai_response ?? response?.decision?.ai_response_json ?? null
    toastStore.success('Decision processed successfully.')
  } catch (error) {
    applySubmitError(error)

    if (error?.error === 'doctrine_required') {
      toastStore.warning('Doctrine required before decision analysis.')
      return
    }

    if (error?.error === 'daily_checkin_required') {
      toastStore.warning('Complete today check-in before decision analysis.')
      return
    }

    if (error?.error === 'openrouter_unavailable' || error?.error === 'ai_response_invalid') {
      toastStore.error('AI service unavailable right now. Retry in a moment.')
      return
    }

    toastStore.error(error?.message ?? 'Unable to submit decision.')
  }
}

const requiresDoctrine = computed(() => submitError.code === 'doctrine_required')
const requiresCheckin = computed(() => submitError.code === 'daily_checkin_required')
const retryableAiError = computed(
  () =>
    submitError.code === 'openrouter_unavailable' ||
    submitError.code === 'ai_response_invalid',
)
</script>

<template>
  <div class="space-y-6">
    <BaseCard elevated title="Ask Decision" subtitle="Submit one structured decision request for doctrine analysis.">
      <form class="space-y-6" @submit.prevent="submit">
        <section class="space-y-4">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-[var(--text)]" for="decision-category">Category</label>
            <select
              id="decision-category"
              v-model="form.category"
              class="focus-ring w-full rounded-xl border border-default bg-surface px-4 py-2.5 text-sm text-[var(--text)]"
              :aria-invalid="errors.category ? 'true' : 'false'"
            >
              <option v-for="category in categories" :key="category" :value="category">
                {{ category }}
              </option>
            </select>
            <p v-if="errors.category" class="text-xs text-[var(--danger)]" role="alert">{{ errors.category }}</p>
          </div>

          <BaseTextarea
            id="decision-what"
            v-model="form.what"
            label="What"
            placeholder="What are you considering doing?"
            :rows="3"
            :error="errors.what"
          />

          <BaseTextarea
            id="decision-why"
            v-model="form.why"
            label="Why"
            placeholder="Why do you want to do this?"
            :rows="3"
            :error="errors.why"
          />

          <BaseInput
            id="decision-when"
            v-model="form.when"
            label="When"
            placeholder="Right now / tonight / this week"
            :error="errors.when"
          />

          <div class="space-y-2">
            <label class="block text-sm font-medium text-[var(--text)]" for="decision-urgency">Urgency</label>
            <select
              id="decision-urgency"
              v-model="form.urgency"
              class="focus-ring w-full rounded-xl border border-default bg-surface px-4 py-2.5 text-sm text-[var(--text)]"
              :aria-invalid="errors.urgency ? 'true' : 'false'"
            >
              <option v-for="urgency in urgencies" :key="urgency" :value="urgency">
                {{ urgency }}
              </option>
            </select>
            <p v-if="errors.urgency" class="text-xs text-[var(--danger)]" role="alert">{{ errors.urgency }}</p>
          </div>

          <BaseTextarea
            id="decision-impact"
            v-model="form.estimatedImpact"
            label="Estimated impact"
            placeholder="What changes if you do this?"
            :rows="3"
            :error="errors.estimatedImpact"
          />
        </section>

        <section class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-[var(--text)]">Alternatives</h3>
            <BaseButton
              type="button"
              size="sm"
              variant="ghost"
              :disabled="form.alternatives.length >= 6"
              @click="addAlternative"
            >
              <Plus class="h-4 w-4" />
              Add
            </BaseButton>
          </div>

          <div class="space-y-3">
            <div
              v-for="(alternative, index) in form.alternatives"
              :key="`alternative-${index}`"
              class="rounded-xl border border-default bg-surface p-3"
            >
              <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                <BaseInput
                  :id="`alternative-${index}`"
                  v-model="form.alternatives[index]"
                  :label="`Alternative ${index + 1}`"
                  placeholder="Alternative option"
                  :error="errors.alternatives[index]"
                />
                <div class="pt-7">
                  <BaseButton
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="form.alternatives.length <= 1"
                    @click="removeAlternative(index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </BaseButton>
                </div>
              </div>
            </div>
          </div>
        </section>

        <div
          v-if="submitError.message"
          class="rounded-xl border border-[rgb(188,125,125,0.4)] bg-[rgb(188,125,125,0.12)] p-4"
        >
          <p class="text-sm font-medium text-[rgb(240,208,208)]">{{ submitError.message }}</p>

          <div class="mt-3 flex flex-wrap gap-2">
            <BaseButton v-if="requiresDoctrine" type="button" size="sm" variant="secondary" @click="router.push('/settings/doctrine')">
              Go to doctrine settings
            </BaseButton>

            <BaseButton v-if="requiresCheckin" type="button" size="sm" variant="secondary" @click="router.push('/checkin')">
              Complete daily check-in
            </BaseButton>

            <BaseButton v-if="retryableAiError" type="button" size="sm" variant="secondary" @click="retry">
              <RefreshCw class="h-4 w-4" />
              Retry
            </BaseButton>
          </div>
        </div>

        <p v-if="errors.general" class="text-sm text-[var(--danger)]">{{ errors.general }}</p>

        <div class="flex items-center justify-end">
          <BaseButton type="submit" :loading="isSubmitting" :disabled="isSubmitting">Run decision analysis</BaseButton>
        </div>
      </form>
    </BaseCard>

    <VerdictCard v-if="aiResponse" :response="aiResponse" />
  </div>
</template>
