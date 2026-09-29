import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: { name: 'tasks' } },
        { path: 'tasks', name: 'tasks', component: () => import('@/views/TasksView.vue') },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: { name: 'tasks' } },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.matched.some((record) => record.meta.requiresAuth) && !auth.user) {
    return { name: 'login' }
  }
  if (to.meta.guestOnly && auth.user) {
    return { name: 'tasks' }
  }
})

export default router
