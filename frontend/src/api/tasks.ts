import { http } from './http'
import type { Paginated, Task, TaskPayload, TaskQuery } from '@/types'

export const tasksApi = {
  async list(query: TaskQuery): Promise<Paginated<Task>> {
    const { data } = await http.get<Paginated<Task>>('/tasks', { params: query })
    return data
  },
  async get(id: number): Promise<Task> {
    const { data } = await http.get<{ data: Task }>(`/tasks/${id}`)
    return data.data
  },
  async create(payload: TaskPayload): Promise<Task> {
    const { data } = await http.post<{ data: Task }>('/tasks', payload)
    return data.data
  },
  async update(id: number, payload: TaskPayload): Promise<Task> {
    const { data } = await http.put<{ data: Task }>(`/tasks/${id}`, payload)
    return data.data
  },
  async remove(id: number): Promise<void> {
    await http.delete(`/tasks/${id}`)
  },
}
