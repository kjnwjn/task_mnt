import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { authApi } from '@/api/auth'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const isAdmin = computed(() => user.value?.role === 'admin')

  async function fetchMe(): Promise<void> {
    try {
      user.value = await authApi.me()
    } catch {
      user.value = null // 401 simply means "not logged in"
    }
  }

  async function login(email: string, password: string): Promise<void> {
    user.value = await authApi.login(email, password)
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } finally {
      user.value = null
    }
  }

  function clear(): void {
    user.value = null
  }

  return { user, isAdmin, fetchMe, login, logout, clear }
})
