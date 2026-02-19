<script setup>
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
</script>

<template>
  <div class="space-y-6">
    <BaseCard elevated title="Command Center" subtitle="Central view for your daily execution system.">
      <div class="flex flex-wrap items-center gap-3">
        <BaseBadge variant="accent">Phase 5 Placeholder</BaseBadge>
        <BaseBadge variant="success">Ready</BaseBadge>
      </div>
      <p class="mt-4 text-sm text-muted">
        Dashboard modules will display active mission status, doctrine alignment, and weekly momentum.
      </p>
    </BaseCard>
  </div>
</template>
