<script setup lang="ts">
import { computed } from 'vue'
import { STATUS_LABELS } from '@/constants'
import type { TaskStatus } from '@/types'

const props = defineProps<{ status: TaskStatus }>()

const COLORS: Record<TaskStatus, string> = {
  todo: 'grey',
  in_progress: 'blue',
  done: 'green',
}

const color = computed(() => COLORS[props.status])
const label = computed(() => STATUS_LABELS[props.status])
</script>

<template>
  <v-chip :color="color" size="small" variant="flat">{{ label }}</v-chip>
</template>
