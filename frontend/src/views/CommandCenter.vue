<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import { useCheckinStore } from '@/stores/checkin'
import { useDecisionStore } from '@/stores/decision'
import { useDisciplineStore } from '@/stores/discipline'
import { useDoctrineStore } from '@/stores/doctrine'
import { useToastStore } from '@/stores/toast'

const router = useRouter()
const doctrineStore = useDoctrineStore()
const checkinStore = useCheckinStore()
const decisionStore = useDecisionStore()
const disciplineStore = useDisciplineStore()
const toastStore = useToastStore()

const loading = ref(false)

const doctrineReady = computed(() => Boolean(doctrineStore.doctrine))
const todayCheckin = computed(() => checkinStore.todayCheckin)
const checkinDone = computed(() => Boolean(todayCheckin.value))
const streak = computed(
  () =>
    disciplineStore.streak ?? {
      current_streak: 0,
      longest_streak: 0,
      last_broken_date: null,
    },
)
const lastDecision = computed(() => decisionStore.lastDecision)

const verdictVariant = computed(() => {
  const verdict = lastDecision.value?.ai_response_json?.verdict

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
})

const loadDashboard = async () => {
  loading.value = true

  try {
    await Promise.all([
      doctrineStore.load(),
      checkinStore.loadToday(),
      decisionStore.listDecisions(),
      disciplineStore.loadStreak(),
    ])
  } catch (error) {
    toastStore.error(error.message ?? 'Unable to load dashboard data.')
  } finally {
    loading.value = false
  }
}

onMounted(loadDashboard)
</script>

<template>
  <div class="space-y-6">
    <BaseCard v-if="loading" elevated>
      <div class="flex items-center justify-center py-12 text-muted">
        <BaseSpinner size="lg" />
        <span class="ml-3 text-sm">Loading command center...</span>
      </div>
    </BaseCard>

    <template v-else>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <BaseCard elevated title="Doctrine Status" subtitle="Rule framework">
          <BaseBadge :variant="doctrineReady ? 'success' : 'warning'">
            {{ doctrineReady ? 'Set' : 'Not set' }}
          </BaseBadge>
          <div class="mt-4">
            <BaseButton type="button" size="sm" variant="secondary" @click="router.push('/settings/doctrine')">
              {{ doctrineReady ? 'Review doctrine' : 'Set doctrine' }}
            </BaseButton>
          </div>
        </BaseCard>

        <BaseCard elevated title="Today Check-In" subtitle="Daily state lock">
          <BaseBadge :variant="checkinDone ? 'success' : 'warning'">
            {{ checkinDone ? 'Completed' : 'Pending' }}
          </BaseBadge>
          <div class="mt-4">
            <BaseButton type="button" size="sm" variant="secondary" @click="router.push('/checkin')">
              {{ checkinDone ? 'View check-in' : 'Complete check-in' }}
            </BaseButton>
          </div>
        </BaseCard>

        <BaseCard elevated title="Current Streak" subtitle="Discipline days">
          <p class="text-3xl font-semibold text-[var(--text)]">{{ streak.current_streak }}</p>
          <p class="mt-1 text-sm text-muted">Longest streak: {{ streak.longest_streak }}</p>
          <p class="mt-2 text-xs text-muted">
            Last broken: {{ streak.last_broken_date || 'No breaks recorded' }}
          </p>
        </BaseCard>

        <BaseCard elevated title="Last Decision" subtitle="Most recent analysis">
          <template v-if="lastDecision">
            <div class="flex items-center gap-2">
              <BaseBadge variant="accent">{{ lastDecision.category }}</BaseBadge>
              <BaseBadge :variant="verdictVariant">
                {{ lastDecision.ai_response_json?.verdict || 'pending' }}
              </BaseBadge>
            </div>
            <p class="mt-3 text-sm text-muted line-clamp-2">
              {{ lastDecision.context_json?.what || 'No context available.' }}
            </p>
          </template>
          <p v-else class="text-sm text-muted">No decisions yet.</p>
          <div class="mt-4">
            <BaseButton type="button" size="sm" @click="router.push('/decision')">
              New decision
            </BaseButton>
          </div>
        </BaseCard>
      </div>

      <BaseCard elevated title="Today's Focus" subtitle="Primary missions from today check-in.">
        <template v-if="todayCheckin">
          <ul class="space-y-2">
            <li
              v-for="(mission, index) in todayCheckin.missions_json"
              :key="`focus-${index}`"
              class="rounded-xl border border-default bg-surface px-4 py-3 text-sm text-[var(--text)]"
            >
              <span class="mr-2 text-accent">{{ index + 1 }}.</span>{{ mission }}
            </li>
          </ul>
          <p class="mt-4 text-sm text-muted">
            {{ todayCheckin.notes || 'No additional context provided for today.' }}
          </p>
        </template>
        <template v-else>
          <p class="text-sm text-muted">No check-in yet. Define today’s focus to unlock better decisions.</p>
          <div class="mt-4">
            <BaseButton type="button" size="sm" @click="router.push('/checkin')">Create today check-in</BaseButton>
          </div>
        </template>
      </BaseCard>
    </template>
  </div>
</template>
