<template>
  <div class="login-page">
    <div class="login-container">
      <div class="login-card">
        <div class="login-header">
          <h1>ByFly Payment Center</h1>
          <p>Панель управления платежами</p>
        </div>

        <div v-if="error" class="alert alert-error">
          {{ error }}
        </div>

        <div v-if="!codeSent">
          <form @submit.prevent="handleSendCode">
            <PhoneInput
              v-model="phone"
              label="Номер телефона"
              default-country="Казахстан"
            />

            <button 
              type="submit" 
              class="btn btn-primary btn-block mt-3"
              :disabled="loading || !phone"
            >
              {{ loading ? 'Отправка...' : 'Получить код' }}
            </button>
          </form>
        </div>

        <div v-else>
          <p class="code-sent-message">
            Код отправлен на номер <strong>{{ phone }}</strong>
          </p>

          <form @submit.prevent="handleVerifyCode">
            <div class="form-group">
              <label class="form-label">Код из SMS</label>
              <input
                type="text"
                v-model="code"
                class="form-control text-center code-input"
                placeholder="000000"
                maxlength="6"
                autofocus
              />
            </div>

            <button 
              type="submit" 
              class="btn btn-primary btn-block mt-3"
              :disabled="loading || code.length < 6"
            >
              {{ loading ? 'Проверка...' : 'Войти' }}
            </button>

            <button 
              type="button" 
              class="btn btn-outline btn-block mt-2"
              @click="resetForm"
              :disabled="loading"
            >
              Изменить номер
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import PhoneInput from '@/components/PhoneInput.vue'

const router = useRouter()
const authStore = useAuthStore()

const phone = ref('')
const code = ref('')
const codeSent = ref(false)
const loading = ref(false)
const error = ref('')

async function handleSendCode() {
  loading.value = true
  error.value = ''

  try {
    await authStore.sendCode(phone.value)
    codeSent.value = true
  } catch (err) {
    error.value = err.response?.data?.message || 'Ошибка отправки кода'
  } finally {
    loading.value = false
  }
}

async function handleVerifyCode() {
  loading.value = true
  error.value = ''

  try {
    await authStore.verifyCode(phone.value, code.value)
    
    // Даем время на сохранение токена
    await new Promise(resolve => setTimeout(resolve, 100))
    
    // Используем replace вместо push, чтобы нельзя было вернуться на логин
    router.replace('/')
  } catch (err) {
    error.value = err.response?.data?.message || 'Неверный код'
    code.value = ''
  } finally {
    loading.value = false
  }
}

function resetForm() {
  codeSent.value = false
  code.value = ''
  error.value = ''
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 1rem;
  position: relative;
  overflow: hidden;
}

.login-page::before {
  content: '';
  position: absolute;
  width: 500px;
  height: 500px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  border-radius: 50%;
  top: -250px;
  right: -250px;
  animation: pulse 4s infinite;
}

.login-page::after {
  content: '';
  position: absolute;
  width: 400px;
  height: 400px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
  border-radius: 50%;
  bottom: -200px;
  left: -200px;
  animation: pulse 5s infinite reverse;
}

.login-container {
  width: 100%;
  max-width: 460px;
  z-index: 10;
  animation: fadeIn 0.6s ease-out;
}

.login-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(20px);
  border-radius: 1.5rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  padding: 3rem;
  border: 1px solid rgba(255, 255, 255, 0.3);
  animation: slideInRight 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.login-header {
  text-align: center;
  margin-bottom: 2.5rem;
}

.login-header h1 {
  font-size: 2.25rem;
  font-weight: 800;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 0.75rem;
  letter-spacing: -1px;
}

.login-header p {
  color: var(--text-light);
  font-size: 1rem;
  font-weight: 500;
}

.code-sent-message {
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
  padding: 1.25rem;
  border-radius: 1rem;
  margin-bottom: 1.5rem;
  font-size: 0.9375rem;
  text-align: center;
  border: 1px solid rgba(102, 126, 234, 0.2);
  animation: fadeIn 0.4s ease-out;
}

.code-input {
  font-size: 1.75rem;
  letter-spacing: 0.75rem;
  font-weight: 700;
  text-align: center;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
}

.btn-block {
  width: 100%;
  padding: 1rem 1.5rem;
  font-size: 1rem;
}
</style>

