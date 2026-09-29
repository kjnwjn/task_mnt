import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import vuetify from './plugins/vuetify'
import { authApi } from './api/auth'
import { onUnauthorized } from './api/http'
import { useAuthStore } from './stores/auth'

async function bootstrap(): Promise<void> {
  const app = createApp(App)
  app.use(createPinia())
  app.use(vuetify)

  const auth = useAuthStore()
  onUnauthorized(() => {
    auth.clear()
    router.push({ name: 'login' })
  })

  // Prime the XSRF cookie, then restore an existing session before the first navigation.
  await authApi.csrf().catch(() => undefined)
  await auth.fetchMe()

  app.use(router)
  await router.isReady()
  app.mount('#app')
}

bootstrap()
