import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('token') || null)
  const adminData = localStorage.getItem('admin')
  const admin = ref(adminData && adminData !== 'null' ? JSON.parse(adminData) : null)

  const isAuthenticated = computed(() => !!token.value && !!admin.value)

  async function sendCode(phone) {
    await api.post('/auth/send-code', { phone })
  }

  async function verifyCode(phone, code) {
    console.log('🔐 AUTH: Verifying code...', { phone, code })
    const response = await api.post('/auth/verify', { phone, code })
    console.log('📥 AUTH: Response:', response)
    
    if (!response || !response.data) {
      console.error('❌ AUTH: Invalid response structure')
      throw new Error('Неверный формат ответа от сервера')
    }
    
    const tokenValue = response.data.token
    const adminValue = response.data.admin
    
    console.log('🎫 AUTH: Token:', tokenValue ? 'exists' : 'missing')
    console.log('👤 AUTH: Admin:', adminValue)
    
    if (!tokenValue || !adminValue) {
      console.error('❌ AUTH: Missing token or admin')
      throw new Error('Отсутствуют данные авторизации')
    }
    
    token.value = tokenValue
    admin.value = adminValue

    localStorage.setItem('token', tokenValue)
    localStorage.setItem('admin', JSON.stringify(adminValue))
    
    console.log('✅ AUTH: Saved to localStorage')
    console.log('📦 Token:', localStorage.getItem('token') ? 'saved' : 'NOT SAVED!')
    console.log('📦 Admin:', localStorage.getItem('admin') ? 'saved' : 'NOT SAVED!')

    return response.data
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch (e) {
      // Ignore errors
    }

    token.value = null
    admin.value = null

    localStorage.removeItem('token')
    localStorage.removeItem('admin')
  }

  return {
    token,
    admin,
    isAuthenticated,
    sendCode,
    verifyCode,
    logout
  }
})

