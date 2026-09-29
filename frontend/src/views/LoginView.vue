<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/api/http'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

async function submit(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    await auth.login(email.value, password.value)
    await router.push({ name: 'tasks' })
  } catch (e) {
    error.value = errorMessage(e, 'Login failed.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <v-main>
    <v-container class="fill-height justify-center">
      <v-card title="Sign in" width="420">
        <v-card-text>
          <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
          <v-form @submit.prevent="submit">
            <v-text-field v-model="email" label="Email" type="email" autocomplete="username" />
            <v-text-field
              v-model="password"
              label="Password"
              type="password"
              autocomplete="current-password"
            />
            <v-btn type="submit" color="primary" block :loading="loading">Login</v-btn>
          </v-form>
        </v-card-text>
      </v-card>
    </v-container>
  </v-main>
</template>
