import { http } from './http'
import type { UserOption } from '@/types'

export const usersApi = {
  async list(): Promise<UserOption[]> {
    const { data } = await http.get<UserOption[]>('/users')
    return data
  },
}
