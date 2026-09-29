<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout(): Promise<void> {
  try {
    await auth.logout()
  } finally {
    await router.push({ name: 'login' })
  }
}
</script>

<template>
  <v-app-bar color="primary" density="comfortable">
    <v-app-bar-title>Team Task Manager</v-app-bar-title>
    <template #append>
      <span class="mr-2">{{ auth.user?.name }}</span>
      <v-chip size="small" variant="elevated" class="mr-4">{{ auth.user?.role }}</v-chip>
      <v-btn variant="text" prepend-icon="mdi-logout" @click="logout">Logout</v-btn>
    </template>
  </v-app-bar>
  <v-main>
    <v-container fluid>
      <router-view />
    </v-container>
  </v-main>
</template>
