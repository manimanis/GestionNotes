import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/LoginView.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/register',
    name: 'Register',
    component: () => import('@/views/RegisterView.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/',
    name: 'Dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/profile',
    name: 'Profil',
    component: () => import('@/views/ProfileView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/feuilles',
    name: 'Feuilles',
    component: () => import('@/views/FeuillesView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/feuilles/:id',
    name: 'FeuilleDetail',
    component: () => import('@/views/FeuilleDetailView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/feuilles/:id/stats',
    name: 'FeuilleStats',
    component: () => import('@/views/FeuilleStatsView.vue'),
    meta: { requiresAuth: true }
  }
]

const router = createRouter({
  history: createWebHistory('/GestionNotes/'),
  routes
})

// Navigation guard pour l'authentification
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
  } else if (!to.meta.requiresAuth && authStore.isAuthenticated && to.path === '/login') {
    next('/')
  } else {
    next()
  }
})

export default router