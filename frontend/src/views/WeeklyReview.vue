<script setup>
import { computed, onMounted, ref } from 'vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import { useToastStore } from '@/stores/toast'
import { useWeeklyReviewStore } from '@/stores/weeklyReview'

const weeklyReviewStore = useWeeklyReviewStore()
const toastStore = useToastStore()

const selectedReviewId = ref(null)
const loadingInitial = ref(false)
const pageError = ref('')

const reviews = computed(() => weeklyReviewStore.reviews)
const latestReview = computed(() => weeklyReviewStore.latestReview)
const selectedReview = computed(() => {
  if (selectedReviewId.value == null) {
    return null
  }

  return reviews.value.find((review) => review.id === selectedReviewId.value) ?? null
})

const previousReviews = computed(() =>
  latestReview.value
    ? reviews.value.filter((review) => review.id !== latestReview.value.id)
    : reviews.value,
)

const formatDate = (value) => {
  if (!value) {
    return 'Unknown'
  }

  try {
    return new Date(value).toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  } catch (_error) {
    return String(value)
  }
}

const complianceWidth = (review) => `${Math.max(0, Math.min(100, Number(review.ai_review_json.compliance_rate) * 100))}%`
const alignmentWidth = (review) =>
  `${Math.max(0, Math.min(100, Number(review.ai_review_json.doctrine_alignment_score)))}%`

const loadReviews = async () => {
  loadingInitial.value = true
  pageError.value = ''

  try {
    await weeklyReviewStore.list()
  } catch (error) {
    pageError.value = error.message ?? 'Unable to load weekly reviews.'
    toastStore.error(pageError.value)
  } finally {
    loadingInitial.value = false
  }
}

const generate = async () => {
  pageError.value = ''

  try {
    await weeklyReviewStore.generate()
    toastStore.success('Weekly review generated.')
  } catch (error) {
    pageError.value = error.message ?? 'Unable to generate weekly review.'
    toastStore.error(pageError.value)
  }
}

const openDetails = (reviewId) => {
  selectedReviewId.value = reviewId
}

onMounted(loadReviews)
</script>

<template>
  <div class="space-y-6">
    <BaseCard elevated title="Weekly Review" subtitle="Generate and inspect weekly discipline patterns.">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm text-muted">
          Generate the current week analysis based on check-ins, decisions, and discipline logs.
        </div>
        <BaseButton
          type="button"
          :loading="weeklyReviewStore.isGenerating"
          :disabled="weeklyReviewStore.isGenerating"
          @click="generate"
        >
          Generate Weekly Review
        </BaseButton>
      </div>
    </BaseCard>

    <BaseCard v-if="loadingInitial || weeklyReviewStore.isListing" elevated>
      <div class="flex items-center justify-center py-12 text-muted">
        <BaseSpinner size="lg" />
        <span class="ml-3 text-sm">Loading weekly reviews...</span>
      </div>
    </BaseCard>

    <BaseCard
      v-else-if="latestReview"
      elevated
      :title="`Latest Review (${formatDate(latestReview.week_start)} - ${formatDate(latestReview.week_end)})`"
      subtitle="Current week performance summary"
    >
      <div class="space-y-6">
        <div class="rounded-xl border border-default bg-surface p-4">
          <p class="text-sm text-[var(--text)]">{{ latestReview.ai_review_json.week_summary }}</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-xl border border-default bg-surface p-4">
            <div class="flex items-center justify-between text-sm">
              <p class="font-medium text-[var(--text)]">Compliance Rate</p>
              <p class="text-accent">
                {{ Math.round(Number(latestReview.ai_review_json.compliance_rate) * 100) }}%
              </p>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-[rgb(127,149,179,0.2)]">
              <div
                class="h-full rounded-full bg-[linear-gradient(90deg,var(--accent-deep)_0%,var(--accent)_100%)] transition-all duration-500"
                :style="{ width: complianceWidth(latestReview) }"
              />
            </div>
          </div>

          <div class="rounded-xl border border-default bg-surface p-4">
            <div class="flex items-center justify-between text-sm">
              <p class="font-medium text-[var(--text)]">Doctrine Alignment</p>
              <p class="text-accent">{{ latestReview.ai_review_json.doctrine_alignment_score }}/100</p>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-[rgb(181,162,121,0.2)]">
              <div
                class="h-full rounded-full bg-[linear-gradient(90deg,rgb(181,162,121)_0%,rgb(151,134,101)_100%)] transition-all duration-500"
                :style="{ width: alignmentWidth(latestReview) }"
              />
            </div>
          </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
          <div class="rounded-xl border border-default bg-surface p-4">
            <p class="text-sm font-semibold text-[var(--text)]">Patterns detected</p>
            <ul class="mt-3 space-y-2 text-sm text-muted">
              <li
                v-for="(pattern, index) in latestReview.ai_review_json.patterns_detected"
                :key="`pattern-${index}`"
                class="rounded-lg border border-default bg-[rgb(16,24,36,0.72)] px-3 py-2"
              >
                {{ pattern }}
              </li>
            </ul>
          </div>

          <div class="space-y-4">
            <div class="rounded-xl border border-default bg-surface p-4">
              <p class="text-xs uppercase tracking-[0.12em] text-muted">Strongest day</p>
              <BaseBadge class="mt-3" variant="success">{{ latestReview.ai_review_json.strongest_day }}</BaseBadge>
            </div>
            <div class="rounded-xl border border-default bg-surface p-4">
              <p class="text-xs uppercase tracking-[0.12em] text-muted">Weakest day</p>
              <BaseBadge class="mt-3" variant="warning">{{ latestReview.ai_review_json.weakest_day }}</BaseBadge>
            </div>
          </div>
        </div>

        <div class="rounded-xl border border-default bg-surface p-4">
          <p class="text-xs uppercase tracking-[0.12em] text-muted">Override analysis</p>
          <p class="mt-2 text-sm text-muted">{{ latestReview.ai_review_json.override_analysis }}</p>
        </div>

        <div class="rounded-xl border border-[rgb(181,162,121,0.35)] bg-[rgb(181,162,121,0.14)] p-4">
          <p class="text-xs uppercase tracking-[0.12em] text-[rgb(225,209,178)]">Directive</p>
          <p class="mt-2 text-sm font-medium text-[rgb(236,223,199)]">{{ latestReview.ai_review_json.directive }}</p>
        </div>
      </div>
    </BaseCard>

    <BaseCard v-else elevated title="No review yet" subtitle="Generate your first weekly review to start pattern tracking." />

    <BaseCard elevated title="Previous Reviews" subtitle="Open any previous week for details.">
      <div v-if="!previousReviews.length" class="text-sm text-muted">No previous reviews found.</div>

      <div v-else class="space-y-2">
        <button
          v-for="review in previousReviews"
          :key="review.id"
          type="button"
          class="focus-ring w-full rounded-xl border border-default bg-surface px-4 py-3 text-left transition hover:bg-elevated"
          @click="openDetails(review.id)"
        >
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="text-sm font-medium text-[var(--text)]">
                {{ formatDate(review.week_start) }} - {{ formatDate(review.week_end) }}
              </p>
              <p class="mt-1 text-xs text-muted">
                Compliance {{ Math.round(Number(review.ai_review_json.compliance_rate) * 100) }}%
              </p>
            </div>
            <BaseBadge variant="accent">{{ review.ai_review_json.doctrine_alignment_score }}/100</BaseBadge>
          </div>
        </button>
      </div>

      <div v-if="selectedReview" class="mt-5 rounded-xl border border-default bg-surface p-4">
        <p class="text-sm font-semibold text-[var(--text)]">
          {{ formatDate(selectedReview.week_start) }} - {{ formatDate(selectedReview.week_end) }}
        </p>
        <p class="mt-2 text-sm text-muted">{{ selectedReview.ai_review_json.week_summary }}</p>
      </div>

      <p v-if="pageError" class="mt-4 text-sm text-[var(--danger)]">{{ pageError }}</p>
    </BaseCard>
  </div>
</template>
