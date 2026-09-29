<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import StatusChip from '@/components/StatusChip.vue'
import TaskForm from '@/components/TaskForm.vue'
import { tasksApi } from '@/api/tasks'
import { errorMessage } from '@/api/http'
import { STATUS_OPTIONS } from '@/constants'
import type { Task, TaskStatus } from '@/types'

const headers = [
  { title: 'Title', key: 'title', sortable: false },
  { title: 'Assignee', key: 'assignee.name', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Due date', key: 'due_date', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' as const },
]

const items = ref<Task[]>([])
const total = ref(0)
const loading = ref(false)
const error = ref<string | null>(null)

const page = ref(1)
const itemsPerPage = ref(10)
const status = ref<TaskStatus | null>(null)
const search = ref<string | null>('')
const debouncedSearch = ref('')

let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    debouncedSearch.value = (value ?? '').trim()
  }, 300)
})

// Filter change → back to page 1. Changing page triggers update:options → load().
watch([status, debouncedSearch], () => {
  if (page.value !== 1) page.value = 1
  else load()
})

let latestRequest = 0

async function load(): Promise<void> {
  const requestId = ++latestRequest
  loading.value = true
  error.value = null
  try {
    const result = await tasksApi.list({
      page: page.value,
      per_page: itemsPerPage.value,
      status: status.value ?? undefined,
      search: debouncedSearch.value || undefined,
    })
    if (requestId !== latestRequest) return // a newer request superseded this one
    items.value = result.data
    total.value = result.meta.total
    // e.g. after deleting the last row on the last page
    if (result.data.length === 0 && page.value > 1 && page.value > result.meta.last_page) {
      page.value = result.meta.last_page
    }
  } catch (e) {
    if (requestId !== latestRequest) return
    error.value = errorMessage(e, 'Failed to load tasks.')
  } finally {
    if (requestId === latestRequest) loading.value = false
  }
}

// Create / edit dialog
const formOpen = ref(false)
const editingId = ref<number | undefined>()

function openCreate(): void {
  editingId.value = undefined
  formOpen.value = true
}

function openEdit(task: Task): void {
  editingId.value = task.id
  formOpen.value = true
}

function onSaved(): void {
  formOpen.value = false
  notify(editingId.value === undefined ? 'Task created' : 'Task updated')
  load()
}

// Delete confirmation
const deleting = ref<Task | null>(null)
const deleteBusy = ref(false)

async function confirmDelete(): Promise<void> {
  if (!deleting.value) return
  deleteBusy.value = true
  try {
    await tasksApi.remove(deleting.value.id)
    deleting.value = null
    notify('Task deleted')
    load()
  } catch (e) {
    notify(errorMessage(e, 'Failed to delete task.'), 'error')
  } finally {
    deleteBusy.value = false
  }
}

// Snackbar
const snackbar = reactive({ show: false, text: '', color: 'success' })

function notify(text: string, color = 'success'): void {
  Object.assign(snackbar, { show: true, text, color })
}
</script>

<template>
  <v-card>
    <v-card-title class="d-flex align-center flex-wrap ga-4 pt-4">
      <span>Tasks</span>
      <v-spacer />
      <v-text-field
        v-model="search"
        label="Search title"
        prepend-inner-icon="mdi-magnify"
        density="compact"
        hide-details
        clearable
        style="max-width: 260px"
      />
      <v-select
        v-model="status"
        :items="STATUS_OPTIONS"
        label="Status"
        density="compact"
        hide-details
        clearable
        style="max-width: 200px"
      />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">New task</v-btn>
    </v-card-title>

    <v-alert v-if="error" type="error" variant="tonal" class="mx-4 mb-2">
      {{ error }}
      <template #append>
        <v-btn variant="text" @click="load">Retry</v-btn>
      </template>
    </v-alert>

    <v-data-table-server
      v-model:page="page"
      v-model:items-per-page="itemsPerPage"
      :headers="headers"
      :items="items"
      :items-length="total"
      :items-per-page-options="[10, 25, 50]"
      :loading="loading"
      loading-text="Loading tasks..."
      no-data-text="No tasks found"
      @update:options="load"
    >
      <template #item.status="{ item }">
        <StatusChip :status="item.status" />
      </template>
      <template #item.due_date="{ item }">
        {{ item.due_date ?? '—' }}
      </template>
      <template #item.actions="{ item }">
        <v-btn icon="mdi-pencil" size="small" variant="text" title="Edit" @click="openEdit(item)" />
        <v-btn
          icon="mdi-delete"
          size="small"
          variant="text"
          color="error"
          title="Delete"
          @click="deleting = item"
        />
      </template>
    </v-data-table-server>
  </v-card>

  <v-dialog v-model="formOpen" max-width="600">
    <TaskForm
      v-if="formOpen"
      :key="editingId ?? 'new'"
      :task-id="editingId"
      @saved="onSaved"
      @cancel="formOpen = false"
    />
  </v-dialog>

  <v-dialog
    :model-value="deleting !== null"
    max-width="420"
    @update:model-value="(open: boolean) => { if (!open) deleting = null }"
  >
    <v-card title="Delete task?">
      <v-card-text>
        "{{ deleting?.title }}" will be permanently deleted.
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="deleting = null">Cancel</v-btn>
        <v-btn color="error" :loading="deleteBusy" @click="confirmDelete">Delete</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.text }}
  </v-snackbar>
</template>
