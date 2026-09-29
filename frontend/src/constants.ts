import type { TaskStatus } from '@/types'

export const STATUS_LABELS: Record<TaskStatus, string> = {
  todo: 'To do',
  in_progress: 'In progress',
  done: 'Done',
}

export const STATUS_OPTIONS = (Object.keys(STATUS_LABELS) as TaskStatus[]).map((value) => ({
  title: STATUS_LABELS[value],
  value,
}))
