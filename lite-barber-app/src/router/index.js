import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior() {
    return { top: 0, behavior: 'instant' }
  },
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue')
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/RegisterView.vue')
    },
    {
      path: '/admin',
      name: 'admin',
      component: () => import('../views/AdminDashboardView.vue')
    },
    {
      path: '/:slug/booking',
      name: 'booking',
      component: () => import('../views/BookingView.vue')
    },
    {
      path: '/:slug/queue',
      name: 'queue',
      component: () => import('../views/QueueView.vue')
    },
    {
      path: '/:slug/success',
      name: 'success',
      component: () => import('../views/SuccessView.vue')
    },
    {
      path: '/:slug/not-found',
      name: 'shop-not-found',
      component: () => import('../views/ShopNotFoundView.vue')
    },
    {
      path: '/:slug?',
      name: 'shop',
      component: () => import('../views/ShopView.vue')
    }
  ]
})

export default router
