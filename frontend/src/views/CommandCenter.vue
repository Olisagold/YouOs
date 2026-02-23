<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import { isSignInRequiredError } from '@/lib/authErrors'
import { useCommandCenterStore } from '@/stores/commandCenter'
import { useDecisionStore } from '@/stores/decision'
import { useToastStore } from '@/stores/toast'

const router = useRouter()
const commandCenterStore = useCommandCenterStore()
const decisionStore = useDecisionStore()
const toastStore = useToastStore()

const loading = ref(false)
const signInRequired = ref(false)

const doctrineReady = computed(() => commandCenterStore.doctrineConfigured)
const todayCheckin = computed(() => commandCenterStore.todayCheckin)
const checkinDone = computed(() => commandCenterStore.checkinCompleted)
const streak = computed(
  () =>
    commandCenterStore.streak ?? {
      current_streak: 0,
      longest_streak: 0,
      last_broken_date: null,
    },
)
const lastDecision = computed(() => decisionStore.lastDecision ?? decisionStore.decisions[0] ?? null)
const hasFocusMissions = computed(
  () => Array.isArray(todayCheckin.value?.missions_json) && todayCheckin.value.missions_json.length > 0,
)
const showSkeleton = computed(
  () => loading.value && !commandCenterStore.isLoaded && !decisionStore.isListLoaded,
)

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

const loadDashboard = async (options = {}) => {
  const force = options.force === true

  loading.value = true
  signInRequired.value = false

  try {
    await Promise.all([
      commandCenterStore.prefetchStatus({ force }),
      decisionStore.listDecisions({ force, page: 1, perPage: 6 }),
    ])
  } catch (error) {
    if (isSignInRequiredError(error)) {
      signInRequired.value = true
      return
    }

    toastStore.error(error.message ?? 'Unable to load dashboard data.')
  } finally {
    loading.value = false
  }
}

onMounted(() => loadDashboard())
</script>

<template>
  <div class="space-y-6">
    <BaseCard v-if="signInRequired || commandCenterStore.authRequired" elevated>
      <div class="space-y-4 text-center">
        <p class="text-lg font-semibold text-[var(--text)]">Sign in required</p>
        <p class="text-sm text-muted">Your session is missing or expired. Sign in to load command center data.</p>
        <div class="pt-2">
          <BaseButton type="button" @click="router.push('/login')">Go to login</BaseButton>
        </div>
      </div>
    </BaseCard>

    <template v-else-if="showSkeleton">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <BaseCard v-for="index in 4" :key="`dashboard-skeleton-${index}`" elevated>
          <div class="space-y-4">
            <div class="stoic-skeleton h-4 w-1/2 rounded-md" />
            <div class="stoic-skeleton h-8 w-2/3 rounded-lg" />
            <div class="stoic-skeleton h-9 w-28 rounded-xl" />
          </div>
        </BaseCard>
      </div>

      <BaseCard elevated title="Today's Focus" subtitle="Primary missions from today check-in.">
        <div class="space-y-3">
          <div v-for="index in 3" :key="`focus-skeleton-${index}`" class="stoic-skeleton h-11 rounded-xl" />
        </div>
      </BaseCard>
    </template>

    <template v-else>
      <div class="flex justify-end">
        <BaseButton
          type="button"
          size="sm"
          variant="ghost"
          :loading="loading"
          :disabled="loading"
          @click="loadDashboard({ force: true })"
        >
          Refresh status
        </BaseButton>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <BaseCard elevated title="Doctrine Status" subtitle="Rule framework">
          <BaseBadge :variant="doctrineReady ? 'success' : 'warning'">
            {{ doctrineReady ? 'Configured' : 'Missing' }}
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
          <div v-else class="space-y-3">
            <p class="text-sm text-muted">No decisions yet. Run your first doctrine-aligned analysis.</p>
          </div>
          <div class="mt-4">
            <BaseButton type="button" size="sm" @click="router.push('/decision')">
              New decision
            </BaseButton>
          </div>
        </BaseCard>
      </div>

      <BaseCard elevated title="Today's Focus" subtitle="Primary missions from today check-in.">
        <template v-if="todayCheckin && hasFocusMissions">
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
          <p class="text-sm text-muted">No check-in yet. Define your focus to improve decision quality.</p>
          <div class="mt-4">
            <BaseButton type="button" size="sm" @click="router.push('/checkin')">Create today check-in</BaseButton>
          </div>
        </template>
      </BaseCard>
    </template>
  </div>
</template>

