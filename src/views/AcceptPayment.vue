<template>
  <div class="accept-payment-page">
    <!-- Форма ввода суммы -->
    <div v-if="step === 'input'" class="payment-form-container">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">💰 Создать платеж</h3>
        </div>

        <div class="amount-display">
          <div class="amount-label">Сумма платежа</div>
          <div class="amount-value">{{ formatAmount(amount) }} ₸</div>
        </div>

        <!-- Цифровая клавиатура -->
        <div class="numpad">
          <div class="numpad-row">
            <button v-for="num in [1, 2, 3]" :key="num" @click="addDigit(num)" class="numpad-btn">
              {{ num }}
            </button>
          </div>
          <div class="numpad-row">
            <button v-for="num in [4, 5, 6]" :key="num" @click="addDigit(num)" class="numpad-btn">
              {{ num }}
            </button>
          </div>
          <div class="numpad-row">
            <button v-for="num in [7, 8, 9]" :key="num" @click="addDigit(num)" class="numpad-btn">
              {{ num }}
            </button>
          </div>
          <div class="numpad-row">
            <button @click="addDigit('00')" class="numpad-btn">00</button>
            <button @click="addDigit(0)" class="numpad-btn">0</button>
            <button @click="backspace" class="numpad-btn backspace">⌫</button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Описание платежа</label>
          <textarea v-model="description" class="form-control" rows="2" placeholder="Например: Оплата за тур в Турцию"></textarea>
          <small class="form-hint">Клиент увидит это описание на странице оплаты</small>
        </div>

        <button 
          @click="createPayment" 
          class="btn btn-primary btn-lg btn-block" 
          :disabled="amount < 100 || creating"
        >
          <span v-if="creating">Создание...</span>
          <span v-else>Создать ссылку для оплаты</span>
        </button>
      </div>
    </div>

    <!-- Ссылка для клиента -->
    <div v-if="step === 'created'" class="payment-result-container">
      <div class="card result-card">
        <div class="result-header">
          <div class="success-icon">✓</div>
          <h2>Ссылка для оплаты создана!</h2>
          <p>Отправьте ссылку или QR код клиенту</p>
        </div>

        <!-- Информация о платеже -->
        <div class="payment-info">
          <div class="info-row">
            <span>Сумма:</span>
            <strong>{{ formatAmount(amount) }} ₸</strong>
          </div>
          <div class="info-row" v-if="description">
            <span>Описание:</span>
            <strong>{{ description }}</strong>
          </div>
          <div class="info-row">
            <span>ID платежа:</span>
            <strong class="transaction-id">{{ transactionId }}</strong>
          </div>
        </div>

        <!-- QR код со ссылкой -->
        <div class="qr-container">
          <div class="qr-code-wrapper">
            <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="QR Code" class="qr-image" />
            <div v-else class="qr-placeholder">
              <div class="spinner"></div>
            </div>
          </div>
          <p class="qr-hint">Клиент может отсканировать QR код или перейти по ссылке</p>
        </div>

        <!-- Ссылка для оплаты -->
        <div class="payment-link-section">
          <label class="link-label">Ссылка для клиента:</label>
          <div class="link-container">
            <input 
              :value="paymentUrl" 
              readonly 
              class="link-input" 
              ref="linkInput"
            />
            <button @click="copyLink" class="btn btn-outline copy-btn">
              📋 Копировать
            </button>
          </div>
          <div class="share-buttons">
            <button @click="shareWhatsApp" class="btn btn-success">
              📱 WhatsApp
            </button>
            <button @click="shareLink" class="btn btn-primary">
              📤 Поделиться
            </button>
          </div>
        </div>

        <!-- Статус платежа -->
        <div class="payment-status-section">
          <h3>Статус платежа</h3>
          <div class="status-card" :class="statusClass">
            <div class="status-icon">{{ statusIcon }}</div>
            <div class="status-text">
              <div class="status-label">{{ statusLabel }}</div>
              <div class="status-description">{{ statusDescription }}</div>
            </div>
          </div>
        </div>

        <!-- Действия -->
        <div class="action-buttons">
          <button @click="checkStatus" class="btn btn-outline">
            🔄 Проверить статус
          </button>
          <button @click="openPaymentPage" class="btn btn-info">
            👁️ Открыть страницу оплаты
          </button>
          <button @click="newPayment" class="btn btn-primary">
            ➕ Новый платеж
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '@/services/api'
import QRCode from 'qrcode'

const amount = ref(0)
const description = ref('')
const step = ref('input') // input, created
const creating = ref(false)
const transactionId = ref(null)
const paymentUrl = ref('')
const qrCodeDataUrl = ref('')
const linkInput = ref(null)
const currentStatus = ref('pending')

let statusCheckInterval = null

onMounted(() => {
  // Автоматическая проверка статуса если есть активный платеж
  if (transactionId.value) {
    startStatusMonitoring()
  }
})

onUnmounted(() => {
  if (statusCheckInterval) {
    clearInterval(statusCheckInterval)
  }
})

const statusClass = computed(() => {
  const classes = {
    'pending': 'status-pending',
    'processing': 'status-processing',
    'paid': 'status-paid',
    'partially_paid': 'status-partial',
    'failed': 'status-failed',
    'cancelled': 'status-cancelled'
  }
  return classes[currentStatus.value] || 'status-pending'
})

const statusIcon = computed(() => {
  const icons = {
    'pending': '⏳',
    'processing': '🔄',
    'paid': '✅',
    'partially_paid': '⚠️',
    'failed': '❌',
    'cancelled': '🚫'
  }
  return icons[currentStatus.value] || '⏳'
})

const statusLabel = computed(() => {
  const labels = {
    'pending': 'Ожидает оплаты',
    'processing': 'В обработке',
    'paid': 'Оплачено',
    'partially_paid': 'Частично оплачено',
    'failed': 'Ошибка',
    'cancelled': 'Отменено'
  }
  return labels[currentStatus.value] || 'Неизвестно'
})

const statusDescription = computed(() => {
  const descriptions = {
    'pending': 'Клиент еще не открыл страницу оплаты',
    'processing': 'Клиент выбирает способ оплаты',
    'paid': 'Платеж успешно завершен',
    'partially_paid': 'Платеж частично оплачен',
    'failed': 'Произошла ошибка при оплате',
    'cancelled': 'Платеж был отменен'
  }
  return descriptions[currentStatus.value] || ''
})

function addDigit(digit) {
  const newAmount = amount.value * 10 + Number(digit)
  if (newAmount <= 999999999) {
    amount.value = newAmount
  }
}

function backspace() {
  amount.value = Math.floor(amount.value / 10)
}

function formatAmount(value) {
  return Number(value).toLocaleString('ru-RU')
}

async function createPayment() {
  creating.value = true
  
  try {
    // Получаем источник (API token)
    const sourcesResponse = await api.get('/admin/sources')
    const sources = sourcesResponse.data || []
    
    if (sources.length === 0) {
      throw new Error('Не найдено активных источников. Создайте источник в разделе "Источники API".')
    }
    
    const sourceToken = sources[0].api_token

    // Создаем платеж через публичный API
    const response = await fetch('/api/payment/init', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Token': sourceToken
      },
      body: JSON.stringify({
        amount: amount.value,
        currency: 'KZT',
        description: description.value || 'Оплата'
      })
    })
    
    const result = await response.json()
    
    if (!result.success) {
      throw new Error(result.message || 'Ошибка создания платежа')
    }
    
    transactionId.value = result.data.transaction_id
    
    // Формируем ссылку на страницу оплаты
    const baseUrl = window.location.origin
    paymentUrl.value = `${baseUrl}/pay/${transactionId.value}`
    
    // Генерируем QR код со ссылкой
    qrCodeDataUrl.value = await QRCode.toDataURL(paymentUrl.value, {
      width: 300,
      margin: 2,
      color: {
        dark: '#000000',
        light: '#FFFFFF'
      }
    })

    step.value = 'created'
    currentStatus.value = 'pending'
    startStatusMonitoring()
    
  } catch (error) {
    console.error('Payment creation error:', error)
    alert(error.message || 'Ошибка создания платежа')
  } finally {
    creating.value = false
  }
}

function startStatusMonitoring() {
  // Проверяем статус каждые 3 секунды
  statusCheckInterval = setInterval(async () => {
    await checkStatus(true)
  }, 3000)
}

async function checkStatus(silent = false) {
  if (!transactionId.value) return
  
  try {
    const response = await fetch(`/api/payment/${transactionId.value}/status`)
    const result = await response.json()
    
    if (result.success && result.data) {
      const newStatus = result.data.status
      
      // Обновляем статус
      if (newStatus !== currentStatus.value) {
        currentStatus.value = newStatus
        
        if (!silent) {
          alert(`Статус обновлен: ${statusLabel.value}`)
        }
        
        // Если оплачено - показываем уведомление
        if (newStatus === 'paid') {
          if (statusCheckInterval) {
            clearInterval(statusCheckInterval)
          }
          alert('✅ Платеж успешно оплачен!')
        }
      }
    }
  } catch (error) {
    console.error('Status check error:', error)
    if (!silent) {
      alert('Ошибка проверки статуса')
    }
  }
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText(paymentUrl.value)
    alert('✅ Ссылка скопирована в буфер обмена!')
  } catch (error) {
    linkInput.value?.select()
    document.execCommand('copy')
    alert('✅ Ссылка скопирована!')
  }
}

function shareWhatsApp() {
  const text = `Оплатите ${formatAmount(amount.value)} ₸ по ссылке:`
  const url = `https://wa.me/?text=${encodeURIComponent(text + '\n' + paymentUrl.value)}`
  window.open(url, '_blank')
}

function shareLink() {
  if (navigator.share) {
    navigator.share({
      title: 'Ссылка для оплаты',
      text: `Оплатите ${formatAmount(amount.value)} ₸ по ссылке:`,
      url: paymentUrl.value
    })
  } else {
    copyLink()
  }
}

function openPaymentPage() {
  window.open(paymentUrl.value, '_blank')
}

function newPayment() {
  if (statusCheckInterval) {
    clearInterval(statusCheckInterval)
  }
  
  amount.value = 0
  description.value = ''
  step.value = 'input'
  transactionId.value = null
  paymentUrl.value = ''
  qrCodeDataUrl.value = ''
  currentStatus.value = 'pending'
}
</script>

<style scoped>
.accept-payment-page {
  max-width: 600px;
  margin: 0 auto;
  animation: fadeIn 0.5s ease-out;
}

.payment-form-container {
  animation: slideInRight 0.5s ease-out;
}

.amount-display {
  text-align: center;
  padding: 2rem;
  margin: 1rem 0;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
  border-radius: 1rem;
}

.amount-label {
  font-size: 0.875rem;
  color: var(--text-light);
  text-transform: uppercase;
  letter-spacing: 1px;
  font-weight: 600;
}

.amount-value {
  font-size: 3rem;
  font-weight: 800;
  background: var(--bg-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-top: 0.5rem;
}

.numpad {
  margin: 2rem 0;
}

.numpad-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}

.numpad-btn {
  height: 70px;
  border: none;
  border-radius: 1rem;
  font-size: 1.5rem;
  font-weight: 600;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.numpad-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
  background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

.numpad-btn:active {
  transform: translateY(0);
}

.numpad-btn.backspace {
  background: linear-gradient(135deg, #ffd521 0%, #ffb800 100%);
  color: white;
  font-size: 1.75rem;
}

.btn-lg {
  padding: 1.25rem 2rem;
  font-size: 1.125rem;
}

.btn-block {
  width: 100%;
}

.payment-result-container {
  animation: fadeIn 0.6s ease-out;
}

.result-card {
  text-align: center;
}

.result-header {
  margin-bottom: 2rem;
}

.success-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3rem;
  color: white;
  margin: 0 auto 1rem auto;
  box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
}

.result-header h2 {
  font-size: 1.75rem;
  font-weight: 700;
  margin: 1rem 0 0.5rem 0;
  background: var(--bg-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.result-header p {
  color: var(--text-light);
}

.payment-info {
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
  border-radius: 1rem;
  padding: 1.5rem;
  margin-bottom: 2rem;
  text-align: left;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem 0;
  border-bottom: 1px solid rgba(102, 126, 234, 0.1);
}

.info-row:last-child {
  border-bottom: none;
}

.transaction-id {
  font-family: monospace;
  font-size: 0.875rem;
}

.qr-container {
  margin: 2rem 0;
}

.qr-code-wrapper {
  background: white;
  padding: 1.5rem;
  border-radius: 1rem;
  display: inline-block;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.qr-image {
  width: 250px;
  height: 250px;
}

.qr-placeholder {
  width: 250px;
  height: 250px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid rgba(102, 126, 234, 0.2);
  border-top-color: var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.qr-hint {
  margin-top: 1rem;
  color: var(--text-light);
  font-size: 0.875rem;
}

.payment-link-section {
  margin: 2rem 0;
  text-align: left;
}

.link-label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: var(--text-color);
}

.link-container {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.link-input {
  flex: 1;
  padding: 1rem;
  border: 2px solid rgba(102, 126, 234, 0.2);
  border-radius: 0.75rem;
  font-size: 0.875rem;
  background: rgba(102, 126, 234, 0.05);
  font-family: monospace;
}

.copy-btn {
  white-space: nowrap;
}

.share-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}

.payment-status-section {
  margin: 2rem 0;
  text-align: left;
}

.payment-status-section h3 {
  margin-bottom: 1rem;
  color: var(--text-color);
}

.status-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border-radius: 1rem;
  border: 2px solid;
  transition: all 0.3s ease;
}

.status-pending {
  background: linear-gradient(135deg, rgba(107, 114, 128, 0.1) 0%, rgba(75, 85, 99, 0.1) 100%);
  border-color: rgba(107, 114, 128, 0.3);
}

.status-processing {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
  border-color: rgba(59, 130, 246, 0.3);
}

.status-paid {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
  border-color: rgba(16, 185, 129, 0.3);
}

.status-partial {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
  border-color: rgba(245, 158, 11, 0.3);
}

.status-failed,
.status-cancelled {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
  border-color: rgba(239, 68, 68, 0.3);
}

.status-icon {
  font-size: 2.5rem;
}

.status-label {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--text-color);
}

.status-description {
  font-size: 0.875rem;
  color: var(--text-light);
  margin-top: 0.25rem;
}

.action-buttons {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.5rem;
  margin-top: 2rem;
}

.btn-info {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
  .action-buttons {
    grid-template-columns: 1fr;
  }
  
  .amount-value {
    font-size: 2rem;
  }
}
</style>
