export type Role = 'admin' | 'user'
export type TaskStatus = 'todo' | 'in_progress' | 'done'

export interface User {
  id: number
  name: string
  email: string
  role: Role
}

export interface UserOption {
  id: number
  name: string
}

export interface Task {
  id: number
  title: string
  description: string | null
  status: TaskStatus
  due_date: string | null
  assigned_to: number
  assignee: UserOption
  created_at: string
  updated_at: string
}

export interface TaskPayload {
  title: string
  description: string | null
  status: TaskStatus
  due_date: string | null
  assigned_to?: number
}

export interface TaskQuery {
  page?: number
  per_page?: number
  status?: TaskStatus
  assigned_to?: number
  search?: string
}

export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
