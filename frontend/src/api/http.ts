import axios from 'axios'

export const http = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true, // sends the XSRF-TOKEN cookie back as X-XSRF-TOKEN
  headers: { Accept: 'application/json' },
})

let unauthorizedHandler: (() => void) | null = null

/** Registered in main.ts to avoid a circular import between http, router and store. */
export function onUnauthorized(handler: () => void): void {
  unauthorizedHandler = handler
}

http.interceptors.response.use(
  (response) => response,
  (error) => {
    const url: string = error.config?.url ?? ''
    if (error.response?.status === 401 && !['/login', '/me'].includes(url)) {
      unauthorizedHandler?.()
    }
    return Promise.reject(error)
  },
)

export function errorMessage(error: unknown, fallback = 'Something went wrong.'): string {
  if (axios.isAxiosError(error)) {
    return error.response?.data?.message ?? error.message ?? fallback
  }
  return fallback
}

export function validationErrors(error: unknown): Record<string, string[]> {
  if (axios.isAxiosError(error) && error.response?.status === 422) {
    return error.response.data?.errors ?? {}
  }
  return {}
}
