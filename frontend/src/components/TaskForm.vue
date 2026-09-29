<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import type { VForm } from 'vuetify/components'
import { tasksApi } from '@/api/tasks'
import { usersApi } from '@/api/users'
import { errorMessage, validationErrors } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { STATUS_OPTIONS } from '@/constants'
import type { Task, TaskPayload, UserOption } from '@/types'

const props = defineProps<{ taskId?: number }>()
const emit = defineEmits<{
  saved: [task: Task]
  cancel: []
}>()

const auth = useAuthStore()

const formRef = ref<VForm>()
const form = reactive<TaskPayload>({
  title: '',
  description: null,
  status: 'todo',
  due_date: null,
  assigned_to: auth.user?.id,
})
const users = ref<UserOption[]>([])
const loading = ref(false)
const saving = ref(false)
const loadFailed = ref(false)
const error = ref<string | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const dateMenu = ref(false)

const isEdit = computed(() => props.taskId !== undefined)
const titleRules = [(v: string) => !!v?.trim() || 'Title is required']

function parseDate(value: string): Date {
  const [y, m, d] = value.split('-').map(Number)
  return new Date(y, m - 1, d)
}

function formatDate(date: Date): string {
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const dd = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${mm}-${dd}`
}

// v-date-picker works with Date objects; the API uses YYYY-MM-DD strings.
const pickerDate = computed<Date | null>({
  get: () => (form.due_date ? parseDate(form.due_date) : null),
  set: (date) => {
    form.due_date = date ? formatDate(date) : null
    dateMenu.value = false
  },
})

onMounted(async () => {
  loading.value = true
  try {
    const [userList, task] = await Promise.all([
      usersApi.list(),
      props.taskId !== undefined ? tasksApi.get(props.taskId) : Promise.resolve(null),
    ])
    users.value = userList
    if (task) {
      Object.assign(form, {
        title: task.title,
        description: task.description,
        status: task.status,
        due_date: task.due_date,
        assigned_to: task.assigned_to,
      })
    }
  } catch (e) {
    loadFailed.value = true
    error.value = errorMessage(e, 'Failed to load form data.')
  } finally {
    loading.value = false
  }
})

async function submit(): Promise<void> {
  if (loadFailed.value) return
  const { valid } = (await formRef.value?.validate()) ?? { valid: false }
  if (!valid) return

  saving.value = true
  error.value = null
  fieldErrors.value = {}
  try {
    const payload: TaskPayload = { ...form }
    if (!auth.isAdmin) delete payload.assigned_to // server forces self anyway
    const task =
      props.taskId !== undefined
        ? await tasksApi.update(props.taskId, payload)
        : await tasksApi.create(payload)
    emit('saved', task)
  } catch (e) {
    fieldErrors.value = validationErrors(e)
    if (Object.keys(fieldErrors.value).length === 0) {
      error.value = errorMessage(e, 'Failed to save task.')
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <v-card :title="isEdit ? 'Edit task' : 'New task'">
    <v-progress-linear v-if="loading" indeterminate color="primary" />
    <v-form ref="formRef" @submit.prevent="submit">
      <v-card-text>
        <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

        <v-text-field
          v-model="form.title"
          label="Title"
          :rules="titleRules"
          :error-messages="fieldErrors.title"
          :disabled="loading"
        />
        <v-textarea
          v-model="form.description"
          label="Description"
          rows="3"
          :error-messages="fieldErrors.description"
          :disabled="loading"
        />
        <v-select
          v-model="form.status"
          :items="STATUS_OPTIONS"
          label="Status"
          :error-messages="fieldErrors.status"
          :disabled="loading"
        />
        <v-select
          v-model="form.assigned_to"
          :items="users"
          item-title="name"
          item-value="id"
          label="Assignee"
          :disabled="loading || !auth.isAdmin"
          :hint="auth.isAdmin ? undefined : 'Your tasks are always assigned to you'"
          persistent-hint
          :error-messages="fieldErrors.assigned_to"
        />
        <v-menu v-model="dateMenu" :close-on-content-click="false">
          <template #activator="{ props: menuProps }">
            <v-text-field
              v-bind="menuProps"
              :model-value="form.due_date ?? ''"
              label="Due date"
              prepend-inner-icon="mdi-calendar"
              readonly
              clearable
              class="mt-2"
              :error-messages="fieldErrors.due_date"
              :disabled="loading"
              @click:clear="form.due_date = null"
            />
          </template>
          <v-date-picker v-model="pickerDate" />
        </v-menu>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="emit('cancel')">Cancel</v-btn>
        <v-btn type="submit" color="primary" :loading="saving" :disabled="loading || loadFailed">Save</v-btn>
      </v-card-actions>
    </v-form>
  </v-card>
</template>
