<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import { isSignInRequiredError } from '@/lib/authErrors'
import { useDecisionStore } from '@/stores/decision'
import { useToastStore } from '@/stores/toast'

const router = useRouter()
const decisionStore = useDecisionStore()
const toastStore = useToastStore()

const loading = ref(false)
const selectedDecisionId = ref(null)
const activeCategory = ref('all')
const currentPage = ref(1)
const perPage = ref(10)
const signInRequired = ref(false)

const categories = ['all', 'financial', 'health', 'work', 'social', 'mindset', 'other']
const pagination = computed(() => decisionStore.pagination)
const totalPages = computed(() => Math.max(1, Number(pagination.value?.last_page ?? 1)))
const canGoPrev = computed(() => Boolean(pagination.value?.prev_page_url))
const canGoNext = computed(() => Boolean(pagination.value?.next_page_url))
const pageSummary = computed(() => {
  const from = pagination.value?.from ?? 0
  const to = pagination.value?.to ?? 0
  const total = pagination.value?.total ?? filteredDecisions.value.length
  return `Showing ${from}-${to} of ${total}`
})

const filteredDecisions = computed(() => {
  if (activeCategory.value === 'all') {
    return decisionStore.decisions
  }

  return decisionStore.decisions.filter((decision) => decision.category === activeCategory.value)
})

const selectedDecision = computed(() =>
  filteredDecisions.value.find((decision) => decision.id === selectedDecisionId.value) ?? null,
)

const formatDate = (value) => {
  if (!value) {
    return 'Unknown date'
  }

  try {
    return new Date(value).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    })
  } catch (_error) {
    return String(value)
  }
}

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

const load = async (options = {}) => {
  const force = options.force === true
  const targetPage = Number(options.page ?? currentPage.value)

  loading.value = true
  signInRequired.value = false

  try {
    await decisionStore.listDecisions({
      force,
      category: activeCategory.value === 'all' ? undefined : activeCategory.value,
      page: targetPage,
      perPage: perPage.value,
    })
    currentPage.value = Number(decisionStore.pagination?.current_page ?? targetPage)
  } catch (error) {
    if (isSignInRequiredError(error)) {
      signInRequired.value = true
      return
    }

    toastStore.error(error.message ?? 'Unable to load decision history.')
  } finally {
    loading.value = false
  }
}

const applyCategory = async (category) => {
  activeCategory.value = category
  currentPage.value = 1
  selectedDecisionId.value = null
  await load({ force: true, page: 1 })
}

const goToPage = async (page) => {
  const safePage = Number(page)

  if (!Number.isFinite(safePage) || safePage < 1 || safePage > totalPages.value) {
    return
  }

  selectedDecisionId.value = null
  await load({ force: true, page: safePage })
}

onMounted(() => load())
</script>

<template>
  <div class="space-y-6">
    <BaseCard v-if="signInRequired" elevated>
      <div class="space-y-4 text-center">
        <p class="text-lg font-semibold text-[var(--text)]">Sign in required</p>
        <p class="text-sm text-muted">Your session has expired. Sign in to review decision history.</p>
        <div class="pt-2">
          <BaseButton type="button" @click="router.push('/login')">Go to login</BaseButton>
        </div>
      </div>
    </BaseCard>

    <template v-else>
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
        <BaseButton
          type="button"
          size="sm"
          variant="secondary"
          :loading="loading"
          :disabled="loading"
          @click="load({ force: true, page: currentPage })"
        >
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
              <p class="mt-1 text-xs text-muted">{{ decision.category }} · {{ formatDate(decision.created_at) }}</p>
            </div>
            <BaseBadge :variant="verdictVariant(decision.ai_response_json?.verdict)">
              {{ decision.ai_response_json?.verdict || 'pending' }}
            </BaseBadge>
          </div>
        </button>
      </div>
    </BaseCard>

    <BaseCard v-if="pagination && pagination.total > 0" elevated>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted">{{ pageSummary }}</p>
        <div class="flex items-center gap-2">
          <BaseButton type="button" size="sm" variant="ghost" :disabled="!canGoPrev || loading" @click="goToPage(currentPage - 1)">
            Previous
          </BaseButton>
          <BaseBadge variant="accent">Page {{ currentPage }} / {{ totalPages }}</BaseBadge>
          <BaseButton type="button" size="sm" variant="ghost" :disabled="!canGoNext || loading" @click="goToPage(currentPage + 1)">
            Next
          </BaseButton>
        </div>
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
    </template>
  </div>
</template>

