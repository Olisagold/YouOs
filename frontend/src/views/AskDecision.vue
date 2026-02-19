<script setup>
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
</script>

<template>
  <div class="space-y-6">
    <BaseCard elevated title="Ask Decision" subtitle="Submit one structured decision request for doctrine review.">
      <BaseBadge variant="warning">Phase 5 Placeholder</BaseBadge>
      <p class="mt-4 text-sm text-muted">
        Decision submission UI will include category, context, urgency, and alternatives.
      </p>
    </BaseCard>
  </div>
</template>
