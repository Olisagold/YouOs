<script setup>
import { computed, onMounted, ref } from 'vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import { useDecisionStore } from '@/stores/decision'
import { useToastStore } from '@/stores/toast'

const decisionStore = useDecisionStore()
const toastStore = useToastStore()

const loading = ref(false)
const selectedDecisionId = ref(null)
const activeCategory = ref('all')

const categories = ['all', 'financial', 'health', 'work', 'social', 'mindset', 'other']

const filteredDecisions = computed(() => {
  if (activeCategory.value === 'all') {
    return decisionStore.decisions
  }

  return decisionStore.decisions.filter((decision) => decision.category === activeCategory.value)
})

const selectedDecision = computed(() =>
  filteredDecisions.value.find((decision) => decision.id === selectedDecisionId.value) ?? null,
)

const verdictVariant = (verdict) => {
  if (verdict === 'approve') {
    return 'success'
  }

  if (verdict === 'reject') {
    return 'danger'
  }

  if (verdict === 'delay') {
    return 'warning'
  }

  return 'neutral'
}

const load = async (force = false) => {
  loading.value = true

  try {
    await decisionStore.listDecisions({
      force,
      category: activeCategory.value === 'all' ? undefined : activeCategory.value,
    })
  } catch (error) {
    toastStore.error(error.message ?? 'Unable to load decision history.')
  } finally {
    loading.value = false
  }
}

const applyCategory = async (category) => {
  activeCategory.value = category
  selectedDecisionId.value = null
  await load(true)
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <BaseCard elevated title="Decision History" subtitle="Review past verdicts, confidence, and action guidance.">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
          <BaseButton
            v-for="category in categories"
            :key="`category-${category}`"
            type="button"
            size="sm"
            :variant="activeCategory === category ? 'secondary' : 'ghost'"
            @click="applyCategory(category)"
          >
            {{ category }}
          </BaseButton>
        </div>
        <BaseButton type="button" size="sm" variant="secondary" :loading="loading" :disabled="loading" @click="load(true)">
          Refresh
        </BaseButton>
      </div>
    </BaseCard>

    <BaseCard v-if="loading && !filteredDecisions.length" elevated>
      <div class="flex items-center justify-center py-12 text-muted">
        <BaseSpinner size="lg" />
        <span class="ml-3 text-sm">Loading decisions...</span>
      </div>
    </BaseCard>

    <BaseCard v-else-if="!filteredDecisions.length" elevated>
      <p class="text-sm text-muted">No decisions found for this filter.</p>
    </BaseCard>

    <BaseCard v-else elevated>
      <div class="space-y-3">
        <button
          v-for="decision in filteredDecisions"
          :key="decision.id"
          type="button"
          class="focus-ring w-full rounded-xl border border-default bg-surface px-4 py-3 text-left transition hover:bg-elevated"
          @click="selectedDecisionId = decision.id"
        >
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-sm font-medium text-[var(--text)]">{{ decision.context_json.what }}</p>
              <p class="mt-1 text-xs text-muted">{{ decision.category }} · {{ decision.created_at }}</p>
            </div>
            <BaseBadge :variant="verdictVariant(decision.ai_response_json?.verdict)">
              {{ decision.ai_response_json?.verdict || 'pending' }}
            </BaseBadge>
          </div>
        </button>
      </div>
    </BaseCard>

    <BaseCard
      v-if="selectedDecision"
      elevated
      :title="selectedDecision.context_json.what"
      subtitle="Decision detail"
    >
      <div class="space-y-4">
        <div class="rounded-xl border border-default bg-surface p-4">
          <p class="text-xs uppercase tracking-[0.12em] text-muted">Why</p>
          <p class="mt-2 text-sm text-[var(--text)]">{{ selectedDecision.context_json.why }}</p>
        </div>
        <div class="rounded-xl border border-default bg-surface p-4">
          <p class="text-xs uppercase tracking-[0.12em] text-muted">When</p>
          <p class="mt-2 text-sm text-[var(--text)]">{{ selectedDecision.context_json.when }}</p>
        </div>
        <div class="rounded-xl border border-default bg-surface p-4">
          <p class="text-xs uppercase tracking-[0.12em] text-muted">AI Reasoning</p>
          <ul class="mt-2 space-y-2 text-sm text-muted">
            <li
              v-for="(reason, index) in selectedDecision.ai_response_json?.reasoning || []"
              :key="`detail-reason-${index}`"
              class="rounded-lg border border-default bg-[rgb(16,24,36,0.72)] px-3 py-2"
            >
              {{ reason }}
            </li>
          </ul>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
