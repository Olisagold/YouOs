<script setup>
import { computed } from 'vue'
import { AlertTriangle, CheckCircle2, Clock3, ListChecks, ShieldX, Sparkles } from 'lucide-vue-next'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseCard from '@/components/ui/BaseCard.vue'

const props = defineProps({
  response: {
    type: Object,
    required: true,
  },
})

const verdictVariant = computed(() => {
  if (props.response.verdict === 'approve') {
    return 'success'
  }

  if (props.response.verdict === 'reject') {
    return 'danger'
  }

  return 'warning'
})

const verdictIcon = computed(() => {
  if (props.response.verdict === 'approve') {
    return CheckCircle2
  }

  if (props.response.verdict === 'reject') {
    return ShieldX
  }

  return Clock3
})

const confidenceValue = computed(() => {
  const rawValue = Number(props.response.confidence ?? 0)
  const normalized = rawValue > 0 && rawValue <= 1 ? rawValue * 100 : rawValue
  return Math.max(0, Math.min(100, normalized))
})
const confidenceLabel = computed(() => `${confidenceValue.value}%`)
const confidenceToneClass = computed(() => {
  if (confidenceValue.value >= 75) {
    return 'text-[rgb(177,211,193)]'
  }

  if (confidenceValue.value >= 50) {
    return 'text-[rgb(224,207,170)]'
  }

  return 'text-[rgb(230,194,194)]'
})
</script>

<template>
  <BaseCard elevated>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-default bg-[rgb(16,24,36,0.72)] p-4">
        <div>
          <h3 class="text-lg font-semibold text-[var(--text)]">AI Verdict</h3>
          <p class="mt-1 text-sm text-muted">Doctrine-aligned response for your submitted decision.</p>
        </div>
        <div class="flex items-center gap-2">
          <component :is="verdictIcon" class="h-5 w-5 text-accent" />
          <BaseBadge :variant="verdictVariant">{{ response.verdict }}</BaseBadge>
        </div>
      </div>
    </template>

    <div class="space-y-6">
      <div class="rounded-xl border border-default bg-surface p-4">
        <div class="flex items-center justify-between text-sm">
          <p class="font-medium text-[var(--text)]">Confidence</p>
          <p :class="confidenceToneClass" class="font-semibold">{{ confidenceLabel }}</p>
        </div>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-[rgb(127,149,179,0.2)]">
          <div
            class="h-full rounded-full bg-[linear-gradient(90deg,var(--accent-deep)_0%,var(--accent)_100%)] transition-all duration-500"
            :style="{ width: `${confidenceValue}%` }"
          />
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-default bg-surface p-4">
          <p class="flex items-center gap-2 text-sm font-semibold text-[var(--text)]">
            <Sparkles class="h-4 w-4 text-accent" />
            Reasoning
          </p>
          <ul class="mt-3 space-y-2 text-sm text-muted">
            <li
              v-for="(reason, index) in response.reasoning || []"
              :key="`reason-${index}`"
              class="rounded-lg border border-default bg-[rgb(16,24,36,0.72)] px-3 py-2"
            >
              {{ reason }}
            </li>
            <li v-if="!(response.reasoning || []).length" class="rounded-lg border border-default bg-[rgb(16,24,36,0.72)] px-3 py-2">
              No additional reasoning details were returned.
            </li>
          </ul>
        </div>

        <div class="rounded-xl border border-default bg-surface p-4">
          <p class="flex items-center gap-2 text-sm font-semibold text-[var(--text)]">
            <AlertTriangle class="h-4 w-4 text-[var(--warning)]" />
            Risks
          </p>
          <ul v-if="(response.risks || []).length" class="mt-3 space-y-2 text-sm text-muted">
            <li
              v-for="(risk, index) in response.risks || []"
              :key="`risk-${index}`"
              class="rounded-lg border border-default bg-[rgb(16,24,36,0.72)] px-3 py-2"
            >
              {{ risk }}
            </li>
          </ul>
          <p v-else class="mt-3 text-sm text-muted">No explicit risks identified.</p>
        </div>
      </div>

      <div v-if="response.better_option" class="rounded-xl border border-[rgb(181,162,121,0.32)] bg-[rgb(181,162,121,0.12)] p-4">
        <p class="text-xs uppercase tracking-[0.12em] text-[rgb(225,209,178)]">Better Option</p>
        <p class="mt-2 text-sm text-[rgb(236,223,199)]">{{ response.better_option }}</p>
      </div>

      <div class="rounded-xl border border-default bg-surface p-4">
        <p class="flex items-center gap-2 text-sm font-semibold text-[var(--text)]">
          <ListChecks class="h-4 w-4 text-accent" />
          Next Steps
        </p>
        <ol class="mt-3 space-y-2 text-sm text-muted">
          <li
            v-for="(step, index) in response.next_steps || []"
            :key="`step-${index}`"
            class="rounded-lg border border-default bg-[rgb(16,24,36,0.72)] px-3 py-2"
          >
            <span class="mr-2 text-accent">{{ index + 1 }}.</span>{{ step }}
          </li>
          <li v-if="!(response.next_steps || []).length" class="rounded-lg border border-default bg-[rgb(16,24,36,0.72)] px-3 py-2">
            No next-step guidance was returned.
          </li>
        </ol>
      </div>
    </div>
  </BaseCard>
</template>
