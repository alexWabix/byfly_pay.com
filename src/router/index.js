import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  // Публичная страница оплаты (не требует авторизации)
  {
    path: '/pay/:id',
    name: 'PaymentPage',
    component: () => import('@/views/PaymentPage.vue'),
    meta: { public: true }
  },
  // Публичная страница подписания платежа
  {
    path: '/approve-payment',
    name: 'ApprovePayment',
    component: () => import('@/views/ApprovePayment.vue'),
    meta: { public: true }
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { guest: true }
  },
  {
    path: '/',
    component: () => import('@/layouts/AdminLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'Dashboard',
        component: () => import('@/views/Dashboard.vue')
      },
      {
        path: 'admins',
        name: 'Admins',
        component: () => import('@/views/Admins.vue')
      },
      {
        path: 'sources',
        name: 'Sources',
        component: () => import('@/views/Sources.vue')
      },
      {
        path: 'payment-methods',
        name: 'PaymentMethods',
        component: () => import('@/views/PaymentMethods.vue')
      },
      {
        path: 'countries',
        name: 'Countries',
        component: () => import('@/views/Countries.vue')
      },
      {
        path: 'terminals',
        name: 'Terminals',
        component: () => import('@/views/Terminals.vue')
      },
      {
        path: 'transactions',
        name: 'Transactions',
        component: () => import('@/views/Transactions.vue')
      },
      {
        path: 'accept-payment',
        name: 'AcceptPayment',
        component: () => import('@/views/AcceptPayment.vue')
      },
      {
        path: 'api-docs',
        name: 'ApiDocs',
        component: () => import('@/views/ApiDocs.vue')
      },
      {
        path: 'payment-categories',
        name: 'PaymentCategories',
        component: () => import('@/views/PaymentCategories.vue')
      },
      {
        path: 'approval-payments',
        name: 'ApprovalPayments',
        component: () => import('@/views/ApprovalPayments.vue')
      },
      {
        path: 'create-outgoing-payment',
        name: 'CreateOutgoingPayment',
        component: () => import('@/views/CreateOutgoingPayment.vue')
      },
      {
        path: 'banks',
        name: 'Banks',
        component: () => import('@/views/Banks.vue')
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation guard
router.beforeEach((to, from, next) => {
  // Проверяем наличие токена напрямую из localStorage
  const token = localStorage.getItem('token')
  const admin = localStorage.getItem('admin')
  const isAuthenticated = !!token && !!admin && admin !== 'null'
  
  console.log('🛣️ ROUTER GUARD:', {
    to: to.path,
    from: from.path,
    hasToken: !!token,
    hasAdmin: !!admin && admin !== 'null',
    isAuthenticated,
    requiresAuth: to.meta.requiresAuth,
    isGuest: to.meta.guest
  })
  
  // Публичные страницы доступны без авторизации
  if (to.meta.public) {
    console.log('✅ ROUTER: Public page, allowing navigation')
    next()
  }
  // Если требуется авторизация, но пользователь не авторизован
  else if (to.meta.requiresAuth && !isAuthenticated) {
    console.log('⛔ ROUTER: Redirecting to /login')
    next('/login')
  } 
  // Если страница для гостей, но пользователь уже авторизован
  else if (to.meta.guest && isAuthenticated) {
    console.log('➡️ ROUTER: Redirecting to /')
    next('/')
  } 
  // Во всех остальных случаях разрешаем переход
  else {
    console.log('✅ ROUTER: Allowing navigation')
    next()
  }
})

export default router

