<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import { useDoctrineStore } from '@/stores/doctrine'
import { useToastStore } from '@/stores/toast'

const doctrineStore = useDoctrineStore()
const toastStore = useToastStore()

const LIMITS = {
  goals: 10,
  rules: 12,
  habits: 10,
  weeklyTargets: 10,
}

const createGoal = (rank = 1, goal = '') => ({ rank, goal })
const createHabit = (habit = '', trigger = '') => ({ habit, trigger })
const createTarget = (target = '', metric = '', goal = 1, current = 0) => ({
  target,
  metric,
  goal,
  current,
})

const form = reactive({
  goals: [createGoal(1, '')],
  rules: [''],
  habits: [createHabit('', '')],
  weeklyTargets: [createTarget('', '', 1, 0)],
})

const errors = reactive({
  goals: [],
  rules: [],
  habits: [],
  weeklyTargets: [],
  general: '',
})

const hasLoaded = ref(false)
const createMode = ref(false)

const isBusy = computed(() => doctrineStore.isLoading || doctrineStore.isSaving)
const hasDoctrine = computed(() => Boolean(doctrineStore.doctrine))
const showEmptyState = computed(
  () => hasLoaded.value && !isBusy.value && !hasDoctrine.value && !createMode.value,
)
const canEditForm = computed(() => hasDoctrine.value || createMode.value)

const hydrateFromDoctrine = (doctrine) => {
  const goals =
    Array.isArray(doctrine?.goals_json) && doctrine.goals_json.length
      ? doctrine.goals_json
      : [createGoal(1, '')]
  const rules =
    Array.isArray(doctrine?.rules_json) && doctrine.rules_json.length ? doctrine.rules_json : ['']
  const habits =
    Array.isArray(doctrine?.habits_json) && doctrine.habits_json.length
      ? doctrine.habits_json
      : [createHabit('', '')]
  const weeklyTargets =
    Array.isArray(doctrine?.weekly_targets_json) && doctrine.weekly_targets_json.length
      ? doctrine.weekly_targets_json
      : [createTarget('', '', 1, 0)]

  form.goals = goals.map((item, index) =>
    createGoal(Number(item.rank ?? index + 1), String(item.goal ?? '')),
  )
  form.rules = rules.map((item) => String(item ?? ''))
  form.habits = habits.map((item) =>
    createHabit(String(item.habit ?? ''), String(item.trigger ?? '')),
  )
  form.weeklyTargets = weeklyTargets.map((item) =>
    createTarget(
      String(item.target ?? ''),
      String(item.metric ?? ''),
      Number(item.goal ?? 1),
      Number(item.current ?? 0),
    ),
  )
}

const resetToDefaults = () => {
  form.goals = [createGoal(1, '')]
  form.rules = ['']
  form.habits = [createHabit('', '')]
  form.weeklyTargets = [createTarget('', '', 1, 0)]
}

const clearErrors = () => {
  errors.goals = []
  errors.rules = []
  errors.habits = []
  errors.weeklyTargets = []
  errors.general = ''
}

const validate = () => {
  clearErrors()

  if (!form.goals.length) {
    errors.general = 'At least one ranked goal is required.'
  }

  const seenRanks = new Set()

  errors.goals = form.goals.map((goal) => {
    if (!Number.isFinite(Number(goal.rank)) || Number(goal.rank) < 1) {
      return 'Rank must be a number greater than 0.'
    }

    if (seenRanks.has(Number(goal.rank))) {
      return 'Each goal rank must be unique.'
    }

    seenRanks.add(Number(goal.rank))

    if (!goal.goal.trim()) {
      return 'Goal text is required.'
    }

    return ''
  })

  errors.rules = form.rules.map((rule) => (!rule.trim() ? 'Rule text is required.' : ''))

  errors.habits = form.habits.map((habit) => {
    if (!habit.habit.trim()) {
      return 'Habit is required.'
    }

    if (!habit.trigger.trim()) {
      return 'Trigger is required.'
    }

    return ''
  })

  errors.weeklyTargets = form.weeklyTargets.map((target) => {
    if (!target.target.trim()) {
      return 'Target text is required.'
    }

    if (!target.metric.trim()) {
      return 'Metric is required.'
    }

    if (!Number.isFinite(Number(target.goal)) || Number(target.goal) <= 0) {
      return 'Goal must be a number greater than 0.'
    }

    if (!Number.isFinite(Number(target.current)) || Number(target.current) < 0) {
      return 'Current value must be 0 or more.'
    }

    return ''
  })

  const hasRowErrors =
    errors.goals.some(Boolean) ||
    errors.rules.some(Boolean) ||
    errors.habits.some(Boolean) ||
    errors.weeklyTargets.some(Boolean)

  const isMissingRequiredSections =
    !form.goals.length || !form.rules.length || !form.habits.length || !form.weeklyTargets.length

  return !hasRowErrors && !isMissingRequiredSections && !errors.general
}

const addGoal = () => {
  if (form.goals.length >= LIMITS.goals) {
    return
  }

  form.goals.push(createGoal(form.goals.length + 1, ''))
}

const addRule = () => {
  if (form.rules.length >= LIMITS.rules) {
    return
  }

  form.rules.push('')
}

const addHabit = () => {
  if (form.habits.length >= LIMITS.habits) {
    return
  }

  form.habits.push(createHabit('', ''))
}

const addWeeklyTarget = () => {
  if (form.weeklyTargets.length >= LIMITS.weeklyTargets) {
    return
  }

  form.weeklyTargets.push(createTarget('', '', 1, 0))
}

const removeAt = (listName, index) => {
  if (form[listName].length <= 1) {
    return
  }

  form[listName].splice(index, 1)
}

const loadDoctrine = async () => {
  try {
    const doctrine = await doctrineStore.load()

    if (doctrine) {
      hydrateFromDoctrine(doctrine)
      createMode.value = false
    } else {
      resetToDefaults()
    }

    hasLoaded.value = true
  } catch (error) {
    toastStore.error(error.message ?? 'Unable to load doctrine.')
    hasLoaded.value = true
  }
}

const beginCreate = () => {
  createMode.value = true
  resetToDefaults()
}

const submit = async () => {
  if (doctrineStore.isSaving) {
    return
  }

  if (!validate()) {
    toastStore.warning('Fix highlighted doctrine fields before saving.')
    return
  }

  const payload = {
    goals_json: form.goals
      .map((goal) => ({
        rank: Number(goal.rank),
        goal: goal.goal.trim(),
      }))
      .sort((a, b) => a.rank - b.rank),
    rules_json: form.rules.map((rule) => rule.trim()),
    habits_json: form.habits.map((habit) => ({
      habit: habit.habit.trim(),
      trigger: habit.trigger.trim(),
    })),
    weekly_targets_json: form.weeklyTargets.map((target) => ({
      target: target.target.trim(),
      metric: target.metric.trim(),
      current: Number(target.current),
      goal: Number(target.goal),
    })),
  }

  try {
    const saved = await doctrineStore.save(payload)
    hydrateFromDoctrine(saved)
    createMode.value = false
    toastStore.success('Doctrine updated')
  } catch (error) {
    if (error?.details && typeof error.details === 'object') {
      errors.general = 'Server validation failed. Review your doctrine fields and retry.'
    }
    toastStore.error(error.message ?? 'Unable to save doctrine.')
  }
}

onMounted(loadDoctrine)
</script>

<template>
  <div class="space-y-6">
    <BaseCard elevated title="Doctrine Settings" subtitle="Define goals, rules, habits, and measurable weekly targets.">
      <template #header>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-semibold text-[var(--text)]">Doctrine Settings</h2>
            <p class="mt-1 text-sm text-muted">Everything the decision engine uses starts here.</p>
          </div>
          <BaseBadge :variant="hasDoctrine ? 'success' : 'warning'">
            {{ hasDoctrine ? 'Doctrine set' : 'Doctrine missing' }}
          </BaseBadge>
        </div>
      </template>

      <div v-if="!hasLoaded || doctrineStore.isLoading" class="flex items-center justify-center py-20 text-muted">
        <BaseSpinner size="lg" />
        <span class="ml-3 text-sm">Loading doctrine...</span>
      </div>

      <div v-else-if="showEmptyState" class="rounded-2xl border border-default bg-elevated p-8 text-center">
        <h3 class="text-xl font-semibold text-[var(--text)]">No doctrine configured</h3>
        <p class="mx-auto mt-3 max-w-xl text-sm text-muted">
          Create your doctrine to unlock decision guidance aligned with your goals and rules.
        </p>
        <div class="mt-6">
          <BaseButton type="button" @click="beginCreate">Create doctrine</BaseButton>
        </div>
      </div>

      <form v-else-if="canEditForm" class="space-y-8" @submit.prevent="submit">
        <section class="space-y-4">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-[var(--text)]">Goals</h3>
            <BaseButton
              type="button"
              variant="ghost"
              size="sm"
              :disabled="form.goals.length >= LIMITS.goals"
              @click="addGoal"
            >
              <Plus class="h-4 w-4" />
              Add goal
            </BaseButton>
          </div>

          <div class="space-y-3">
            <div
              v-for="(goal, index) in form.goals"
              :key="`goal-${index}`"
              class="rounded-xl border border-default bg-surface p-3"
            >
              <div class="grid gap-3 sm:grid-cols-[100px_1fr_auto]">
                <BaseInput
                  :id="`goal-rank-${index}`"
                  v-model="goal.rank"
                  type="number"
                  label="Rank"
                  min="1"
                  placeholder="1"
                  :error="errors.goals[index] && errors.goals[index].includes('Rank') ? errors.goals[index] : ''"
                />
                <BaseInput
                  :id="`goal-text-${index}`"
                  v-model="goal.goal"
                  label="Goal"
                  placeholder="Build a profitable business"
                  :error="
                    errors.goals[index] && !errors.goals[index].includes('Rank') ? errors.goals[index] : ''
                  "
                />
                <div class="pt-7">
                  <BaseButton
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="form.goals.length <= 1"
                    @click="removeAt('goals', index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </BaseButton>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="space-y-4">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-[var(--text)]">Rules</h3>
            <BaseButton
              type="button"
              variant="ghost"
              size="sm"
              :disabled="form.rules.length >= LIMITS.rules"
              @click="addRule"
            >
              <Plus class="h-4 w-4" />
              Add rule
            </BaseButton>
          </div>

          <div class="space-y-3">
            <div
              v-for="(rule, index) in form.rules"
              :key="`rule-${index}`"
              class="rounded-xl border border-default bg-surface p-3"
            >
              <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                <BaseInput
                  :id="`rule-text-${index}`"
                  v-model="form.rules[index]"
                  label="Rule"
                  placeholder="No impulsive decisions after 8PM"
                  :error="errors.rules[index]"
                />
                <div class="pt-7">
                  <BaseButton
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="form.rules.length <= 1"
                    @click="removeAt('rules', index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </BaseButton>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="space-y-4">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-[var(--text)]">Bad Habits</h3>
            <BaseButton
              type="button"
              variant="ghost"
              size="sm"
              :disabled="form.habits.length >= LIMITS.habits"
              @click="addHabit"
            >
              <Plus class="h-4 w-4" />
              Add habit
            </BaseButton>
          </div>

          <div class="space-y-3">
            <div
              v-for="(habit, index) in form.habits"
              :key="`habit-${index}`"
              class="rounded-xl border border-default bg-surface p-3"
            >
              <div class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                <BaseInput
                  :id="`habit-name-${index}`"
                  v-model="habit.habit"
                  label="Habit"
                  placeholder="Emotional trading"
                  :error="
                    errors.habits[index] && errors.habits[index].includes('Habit') ? errors.habits[index] : ''
                  "
                />
                <BaseInput
                  :id="`habit-trigger-${index}`"
                  v-model="habit.trigger"
                  label="Trigger"
                  placeholder="After losing streak"
                  :error="
                    errors.habits[index] && errors.habits[index].includes('Trigger')
                      ? errors.habits[index]
                      : ''
                  "
                />
                <div class="pt-7">
                  <BaseButton
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="form.habits.length <= 1"
                    @click="removeAt('habits', index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </BaseButton>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="space-y-4">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-[var(--text)]">Weekly Targets</h3>
            <BaseButton
              type="button"
              variant="ghost"
              size="sm"
              :disabled="form.weeklyTargets.length >= LIMITS.weeklyTargets"
              @click="addWeeklyTarget"
            >
              <Plus class="h-4 w-4" />
              Add target
            </BaseButton>
          </div>

          <div class="space-y-3">
            <div
              v-for="(target, index) in form.weeklyTargets"
              :key="`target-${index}`"
              class="rounded-xl border border-default bg-surface p-3"
            >
              <div class="grid gap-3 sm:grid-cols-[1.2fr_1fr_110px_110px_auto]">
                <BaseInput
                  :id="`target-name-${index}`"
                  v-model="target.target"
                  label="Target"
                  placeholder="Deep work sessions"
                  :error="
                    errors.weeklyTargets[index] && errors.weeklyTargets[index].includes('Target')
                      ? errors.weeklyTargets[index]
                      : ''
                  "
                />
                <BaseInput
                  :id="`target-metric-${index}`"
                  v-model="target.metric"
                  label="Metric"
                  placeholder="sessions"
                  :error="
                    errors.weeklyTargets[index] && errors.weeklyTargets[index].includes('Metric')
                      ? errors.weeklyTargets[index]
                      : ''
                  "
                />
                <BaseInput
                  :id="`target-goal-${index}`"
                  v-model="target.goal"
                  type="number"
                  label="Goal"
                  min="1"
                  :error="
                    errors.weeklyTargets[index] && errors.weeklyTargets[index].includes('Goal')
                      ? errors.weeklyTargets[index]
                      : ''
                  "
                />
                <BaseInput
                  :id="`target-current-${index}`"
                  v-model="target.current"
                  type="number"
                  label="Current"
                  min="0"
                  :error="
                    errors.weeklyTargets[index] && errors.weeklyTargets[index].includes('Current')
                      ? errors.weeklyTargets[index]
                      : ''
                  "
                />
                <div class="pt-7">
                  <BaseButton
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="form.weeklyTargets.length <= 1"
                    @click="removeAt('weeklyTargets', index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </BaseButton>
                </div>
              </div>
            </div>
          </div>
        </section>

        <p v-if="errors.general" class="text-sm text-[var(--danger)]">{{ errors.general }}</p>

        <div
          class="sticky bottom-0 -mx-6 mt-8 rounded-b-2xl border-t border-default bg-[rgb(16,24,36,0.95)] px-6 py-4 backdrop-blur"
        >
          <div class="flex items-center justify-between gap-4">
            <p class="text-sm text-muted">Changes apply immediately to decision reasoning.</p>
            <BaseButton type="submit" :loading="doctrineStore.isSaving" :disabled="doctrineStore.isSaving">
              Save doctrine
            </BaseButton>
          </div>
        </div>
      </form>
    </BaseCard>
  </div>
</template>
