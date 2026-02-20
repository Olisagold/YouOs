<script setup>
import { computed } from 'vue'
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

const confidenceValue = computed(() => Math.max(0, Math.min(100, Number(props.response.confidence ?? 0))))
const confidenceLabel = computed(() => `${confidenceValue.value}%`)
</script>

<template>
  <BaseCard elevated>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 class="text-lg font-semibold text-[var(--text)]">AI Verdict</h3>
          <p class="mt-1 text-sm text-muted">Doctrine-aligned response for your submitted decision.</p>
        </div>
        <BaseBadge :variant="verdictVariant">{{ response.verdict }}</BaseBadge>
      </div>
    </template>

    <div class="space-y-6">
      <div class="rounded-xl border border-default bg-surface p-4">
        <div class="flex items-center justify-between text-sm">
          <p class="font-medium text-[var(--text)]">Confidence</p>
          <p class="text-accent">{{ confidenceLabel }}</p>
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
          <p class="text-sm font-semibold text-[var(--text)]">Reasoning</p>
          <ul class="mt-3 space-y-2 text-sm text-muted">
            <li
              v-for="(reason, index) in response.reasoning"
              :key="`reason-${index}`"
              class="rounded-lg border border-default bg-[rgb(16,24,36,0.72)] px-3 py-2"
            >
              {{ reason }}
            </li>
          </ul>
        </div>

        <div class="rounded-xl border border-default bg-surface p-4">
          <p class="text-sm font-semibold text-[var(--text)]">Risks</p>
          <ul v-if="response.risks.length" class="mt-3 space-y-2 text-sm text-muted">
            <li
              v-for="(risk, index) in response.risks"
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
        <p class="text-sm font-semibold text-[var(--text)]">Next Steps</p>
        <ol class="mt-3 space-y-2 text-sm text-muted">
          <li
            v-for="(step, index) in response.next_steps"
            :key="`step-${index}`"
            class="rounded-lg border border-default bg-[rgb(16,24,36,0.72)] px-3 py-2"
          >
            <span class="mr-2 text-accent">{{ index + 1 }}.</span>{{ step }}
          </li>
        </ol>
      </div>
    </div>
  </BaseCard>
</template>
