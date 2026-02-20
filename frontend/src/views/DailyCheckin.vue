<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Plus, Trash2 } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import { useCheckinStore } from '@/stores/checkin'
import { useToastStore } from '@/stores/toast'

const checkinStore = useCheckinStore()
const toastStore = useToastStore()
const router = useRouter()

const form = reactive({
  energy: 7,
  mood: 7,
  missions: [''],
  notes: '',
})

const errors = reactive({
  missions: [],
  general: '',
})

const isSubmitting = ref(false)
const hasLoaded = ref(false)

const todayCheckin = computed(() => checkinStore.todayCheckin)
const isCompleteForToday = computed(() => Boolean(todayCheckin.value))

const clearErrors = () => {
  errors.missions = []
  errors.general = ''
}

const validate = () => {
  clearErrors()

  if (form.missions.length < 1 || form.missions.length > 3) {
    errors.general = 'Missions must include 1 to 3 items.'
  }

  errors.missions = form.missions.map((mission) =>
    !mission.trim() ? 'Mission cannot be empty.' : '',
  )

  if (Number(form.energy) < 1 || Number(form.energy) > 10) {
    errors.general = 'Energy must stay between 1 and 10.'
  }

  if (Number(form.mood) < 1 || Number(form.mood) > 10) {
    errors.general = 'Mood must stay between 1 and 10.'
  }

  return !errors.general && !errors.missions.some(Boolean)
}

const addMission = () => {
  if (form.missions.length >= 3) {
    return
  }

  form.missions.push('')
}

const removeMission = (index) => {
  if (form.missions.length <= 1) {
    return
  }

  form.missions.splice(index, 1)
}

const loadToday = async () => {
  try {
    await checkinStore.loadToday()
  } catch (error) {
    toastStore.error(error.message ?? 'Unable to load today check-in.')
  } finally {
    hasLoaded.value = true
  }
}

const submit = async () => {
  if (isSubmitting.value || checkinStore.isCreating) {
    return
  }

  if (!validate()) {
    toastStore.warning('Fix check-in fields before submitting.')
    return
  }

  isSubmitting.value = true

  try {
    await checkinStore.create({
      energy: Number(form.energy),
      mood: Number(form.mood),
      missions_json: form.missions.map((mission) => mission.trim()),
      notes: form.notes.trim(),
    })

    toastStore.success('Daily check-in completed.')
    router.push('/dashboard')
  } catch (error) {
    if (error?.status === 409 || error?.error === 'daily_checkin_exists') {
      await checkinStore.loadToday({ force: true })
      toastStore.info('Today check-in already exists. Showing completed summary.')
      return
    }

    toastStore.error(error.message ?? 'Unable to submit check-in.')
  } finally {
    isSubmitting.value = false
  }
}

onMounted(loadToday)
</script>

<template>
  <div class="space-y-6">
    <BaseCard elevated title="Daily Check-In" subtitle="Capture your current state before making decisions.">
      <div v-if="!hasLoaded || checkinStore.isLoading" class="flex items-center justify-center py-16 text-muted">
        <BaseSpinner size="lg" />
        <span class="ml-3 text-sm">Loading today check-in...</span>
      </div>

      <div v-else-if="isCompleteForToday && todayCheckin" class="space-y-6">
        <div class="flex flex-wrap items-center gap-2">
          <BaseBadge variant="success">Completed today</BaseBadge>
          <BaseBadge variant="accent">{{ todayCheckin.checkin_date }}</BaseBadge>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div class="rounded-xl border border-default bg-surface p-4">
            <p class="text-xs uppercase tracking-[0.12em] text-muted">Energy</p>
            <p class="mt-2 text-2xl font-semibold text-[var(--text)]">{{ todayCheckin.energy }}/10</p>
          </div>
          <div class="rounded-xl border border-default bg-surface p-4">
            <p class="text-xs uppercase tracking-[0.12em] text-muted">Mood</p>
            <p class="mt-2 text-2xl font-semibold text-[var(--text)]">{{ todayCheckin.mood }}/10</p>
          </div>
        </div>

        <div class="rounded-xl border border-default bg-surface p-4">
          <p class="text-xs uppercase tracking-[0.12em] text-muted">Top Missions</p>
          <ul class="mt-3 space-y-2">
            <li
              v-for="(mission, index) in todayCheckin.missions_json"
              :key="`read-mission-${index}`"
              class="rounded-lg border border-default bg-[rgb(16,24,36,0.7)] px-3 py-2 text-sm text-[var(--text)]"
            >
              {{ mission }}
            </li>
          </ul>
        </div>

        <div class="rounded-xl border border-default bg-surface p-4">
          <p class="text-xs uppercase tracking-[0.12em] text-muted">Notes</p>
          <p class="mt-2 text-sm text-[var(--text)]">
            {{ todayCheckin.notes || 'No notes added for today.' }}
          </p>
        </div>
      </div>

      <form v-else class="space-y-6" @submit.prevent="submit">
        <section class="space-y-4">
          <div class="rounded-xl border border-default bg-surface p-4">
            <div class="flex items-center justify-between text-sm">
              <label class="font-medium text-[var(--text)]" for="energy-slider">Energy</label>
              <span class="text-accent">{{ form.energy }}/10</span>
            </div>
            <input
              id="energy-slider"
              v-model.number="form.energy"
              class="mt-3 h-2 w-full cursor-pointer appearance-none rounded-full bg-[rgb(127,149,179,0.24)] accent-[rgb(127,149,179)]"
              type="range"
              min="1"
              max="10"
              step="1"
            />
          </div>

          <div class="rounded-xl border border-default bg-surface p-4">
            <div class="flex items-center justify-between text-sm">
              <label class="font-medium text-[var(--text)]" for="mood-slider">Mood</label>
              <span class="text-accent">{{ form.mood }}/10</span>
            </div>
            <input
              id="mood-slider"
              v-model.number="form.mood"
              class="mt-3 h-2 w-full cursor-pointer appearance-none rounded-full bg-[rgb(181,162,121,0.26)] accent-[rgb(181,162,121)]"
              type="range"
              min="1"
              max="10"
              step="1"
            />
          </div>
        </section>

        <section class="space-y-4">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-[var(--text)]">Top Missions</h3>
            <BaseButton type="button" variant="ghost" size="sm" :disabled="form.missions.length >= 3" @click="addMission">
              <Plus class="h-4 w-4" />
              Add mission
            </BaseButton>
          </div>

          <div class="space-y-3">
            <div
              v-for="(mission, index) in form.missions"
              :key="`mission-${index}`"
              class="rounded-xl border border-default bg-surface p-3"
            >
              <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                <BaseInput
                  :id="`mission-text-${index}`"
                  v-model="form.missions[index]"
                  :label="`Mission ${index + 1}`"
                  placeholder="Complete the highest-impact task"
                  :error="errors.missions[index]"
                />
                <div class="pt-7">
                  <BaseButton
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="form.missions.length <= 1"
                    @click="removeMission(index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </BaseButton>
                </div>
              </div>
            </div>
          </div>
        </section>

        <BaseTextarea
          id="checkin-notes"
          v-model="form.notes"
          label="Notes (optional)"
          hint="Context for your day. Example: poor sleep, travel, or high-pressure meetings."
          placeholder="Any useful context for decision quality..."
          :rows="4"
        />

        <p v-if="errors.general" class="text-sm text-[var(--danger)]">{{ errors.general }}</p>

        <div class="flex items-center justify-end">
          <BaseButton type="submit" :loading="isSubmitting" :disabled="isSubmitting || checkinStore.isCreating">
            Submit check-in
          </BaseButton>
        </div>
      </form>
    </BaseCard>
  </div>
</template>
