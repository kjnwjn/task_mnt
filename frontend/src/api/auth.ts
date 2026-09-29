import { http } from './http'
import type { User } from '@/types'

export const authApi = {
  async csrf(): Promise<void> {
    await http.get('/csrf')
  },
  async login(email: string, password: string): Promise<User> {
    const { data } = await http.post<{ data: User }>('/login', { email, password })
    return data.data
  },
  async logout(): Promise<void> {
    await http.post('/logout')
  },
  async me(): Promise<User> {
    const { data } = await http.get<{ data: User }>('/me')
    return data.data
  },
}
