<template>
  <div class="admin-layout">
    <aside class="sidebar">
      <div class="sidebar-header">
        <h2>ByFly Pay</h2>
      </div>

      <nav class="sidebar-nav">
        <!-- Основное -->
        <router-link to="/" class="nav-item">
          <span class="nav-icon">📊</span>
          <span>Дашборд</span>
        </router-link>

        <router-link to="/accept-payment" class="nav-item">
          <span class="nav-icon">💰</span>
          <span>Принять оплату</span>
        </router-link>

        <router-link to="/transactions" class="nav-item">
          <span class="nav-icon">💳</span>
          <span>Транзакции</span>
        </router-link>

        <div class="nav-divider"></div>
        <div class="nav-group-title">Согласования</div>

        <router-link to="/approval-payments" class="nav-item">
          <span class="nav-icon">📋</span>
          <span>Платежи</span>
        </router-link>

        <router-link to="/payment-categories" class="nav-item">
          <span class="nav-icon">📁</span>
          <span>Категории</span>
        </router-link>

        <router-link to="/banks" class="nav-item">
          <span class="nav-icon">🏦</span>
          <span>Банки</span>
        </router-link>

        <div class="nav-divider"></div>
        <div class="nav-group-title">Настройки</div>

        <router-link to="/terminals" class="nav-item">
          <span class="nav-icon">🖥️</span>
          <span>Терминалы</span>
        </router-link>

        <router-link to="/sources" class="nav-item">
          <span class="nav-icon">🔌</span>
          <span>Источники API</span>
        </router-link>

        <router-link to="/payment-methods" class="nav-item">
          <span class="nav-icon">💳</span>
          <span>Способы оплаты</span>
        </router-link>

        <router-link to="/countries" class="nav-item">
          <span class="nav-icon">🌍</span>
          <span>Страны</span>
        </router-link>

        <router-link to="/admins" class="nav-item" v-if="authStore.admin?.is_super_admin">
          <span class="nav-icon">👥</span>
          <span>Администраторы</span>
        </router-link>

        <div class="nav-divider"></div>

        <router-link to="/api-docs" class="nav-item">
          <span class="nav-icon">📚</span>
          <span>API Docs</span>
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <div class="user-info">
          <div class="user-name">{{ authStore.admin?.name || 'Админ' }}</div>
          <div class="user-phone">{{ authStore.admin?.phone }}</div>
        </div>
        <button @click="handleLogout" class="btn btn-outline btn-sm">
          Выйти
        </button>
      </div>
    </aside>

    <div class="main-content">
      <header class="header">
        <div class="header-content">
          <h1 class="page-title">{{ pageTitle }}</h1>
        </div>
      </header>

      <main class="content">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const pageTitle = computed(() => {
  const titles = {
    'Dashboard': 'Дашборд',
    'Transactions': 'История транзакций',
    'Terminals': 'Терминалы Kaspi',
    'Sources': 'Источники API',
    'PaymentMethods': 'Способы оплаты',
    'Admins': 'Администраторы'
  }
  return titles[route.name] || 'ByFly Payment Center'
})

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.admin-layout {
  display: flex;
  min-height: 100vh;
}

.sidebar {
  width: 280px;
  background: linear-gradient(180deg, #1a202c 0%, #2d3748 100%);
  color: white;
  display: flex;
  flex-direction: column;
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
  z-index: 100;
}

.sidebar-header {
  padding: 2rem 1.5rem;
  background: var(--bg-gradient);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header h2 {
  font-size: 1.75rem;
  font-weight: 800;
  margin: 0;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
  letter-spacing: -0.5px;
}

.sidebar-nav {
  flex: 1;
  padding: 0.75rem 0;
  overflow-y: auto;
  max-height: calc(100vh - 200px);
}

.sidebar-nav::-webkit-scrollbar {
  width: 4px;
}

.sidebar-nav::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 2px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.5rem;
  margin: 0.25rem 0.75rem;
  color: rgba(255, 255, 255, 0.7);
  text-decoration: none;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border-radius: 0.75rem;
  font-weight: 500;
  position: relative;
  overflow: hidden;
}

.nav-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  height: 100%;
  width: 4px;
  background: var(--bg-gradient);
  transform: scaleY(0);
  transition: transform 0.3s ease;
}

.nav-item:hover {
  background: rgba(102, 126, 234, 0.15);
  color: white;
  transform: translateX(4px);
}

.nav-item.router-link-active {
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.nav-item.router-link-active::before {
  transform: scaleY(1);
}

.nav-icon {
  font-size: 1.5rem;
  transition: transform 0.3s ease;
}

.nav-item:hover .nav-icon {
  transform: scale(1.2) rotate(5deg);
}

.sidebar-footer {
  padding: 1.5rem;
  background: linear-gradient(180deg, transparent 0%, rgba(0, 0, 0, 0.2) 100%);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.user-info {
  margin-bottom: 1rem;
  padding: 1rem;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 0.75rem;
  backdrop-filter: blur(10px);
}

.user-name {
  font-weight: 700;
  font-size: 0.875rem;
}

.user-phone {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.6);
  margin-top: 0.375rem;
}

.btn-sm {
  padding: 0.625rem 1.25rem;
  font-size: 0.75rem;
  width: 100%;
}

.main-content {
  margin-left: 280px;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.header {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 249, 255, 0.95) 100%);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(102, 126, 234, 0.1);
  padding: 2rem 2.5rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
  position: sticky;
  top: 0;
  z-index: 50;
}

.page-title {
  font-size: 2rem;
  font-weight: 800;
  margin: 0;
  background: var(--bg-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  letter-spacing: -0.5px;
}

.content {
  padding: 2.5rem;
  flex: 1;
}

.nav-divider {
  height: 1px;
  background: rgba(255, 255, 255, 0.1);
  margin: 0.5rem 0;
}

.nav-group-title {
  padding: 0.75rem 1.5rem;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: rgba(255, 255, 255, 0.5);
  margin-top: 0.5rem;
}
</style>

