<template>
  <div class="transactions-page">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">История транзакций</h3>
      </div>

      <div class="filters">
        <div class="form-group">
          <select v-model="filters.status" @change="loadTransactions" class="form-select">
            <option value="">Все статусы</option>
            <option value="pending">Ожидание</option>
            <option value="processing">В обработке</option>
            <option value="paid">Оплачено</option>
            <option value="cancelled">Отменено</option>
            <option value="failed">Ошибка</option>
          </select>
        </div>

        <div class="form-group">
          <input 
            type="date" 
            v-model="filters.date_from" 
            @change="loadTransactions"
            class="form-control"
            placeholder="Дата от"
          />
        </div>

        <div class="form-group">
          <input 
            type="date" 
            v-model="filters.date_to" 
            @change="loadTransactions"
            class="form-control"
            placeholder="Дата до"
          />
        </div>
      </div>

      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>ID транзакции</th>
              <th>Сумма</th>
              <th>Описание</th>
              <th>Статус</th>
              <th>Источник</th>
              <th>Клиент</th>
              <th>Дата создания</th>
              <th>Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="transaction in transactions" :key="transaction.id">
              <td>
                <code>{{ transaction.transaction_id.substring(0, 12) }}...</code>
              </td>
              <td>
                <strong>{{ formatAmount(transaction.amount) }} {{ transaction.currency }}</strong>
                <div v-if="transaction.paid_amount > 0" class="text-small">
                  Оплачено: {{ formatAmount(transaction.paid_amount) }}
                </div>
              </td>
              <td>{{ transaction.description || '-' }}</td>
              <td>
                <span :class="'badge badge-' + getStatusClass(transaction.status)">
                  {{ getStatusText(transaction.status) }}
                </span>
              </td>
              <td>{{ getSourceName(transaction.source_id) }}</td>
              <td>
                <div v-if="transaction.client_ip" class="client-info">
                  <div class="client-device">{{ getDeviceIcon(transaction.client_device) }} {{ transaction.client_device || 'Unknown' }}</div>
                  <div class="client-ip text-small">{{ transaction.client_ip }}</div>
                </div>
                <div v-else class="text-small">Не открыт</div>
              </td>
              <td>{{ formatDate(transaction.created_at) }}</td>
              <td>
                <div class="action-buttons">
                  <button 
                    @click="viewDetails(transaction)" 
                    class="btn btn-sm btn-outline"
                    title="Подробнее"
                  >
                    👁️
                  </button>
                  <button 
                    v-if="transaction.status === 'partially_paid'" 
                    @click="markAsPaid(transaction)" 
                    class="btn btn-sm btn-success"
                    title="Отметить как оплачено"
                  >
                    ✅
                  </button>
                  <button 
                    @click="deleteTransaction(transaction.id)" 
                    class="btn btn-sm btn-danger"
                    title="Удалить"
                  >
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!transactions.length && !loading">
              <td colspan="8" class="text-center">Нет транзакций</td>
            </tr>
            <tr v-if="loading">
              <td colspan="8" class="text-center">Загрузка...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal - Детальная информация о транзакции -->
    <div v-if="showDetails && selectedTransaction" class="modal-overlay" @click.self="closeDetails">
      <div class="modal modal-large">
        <div class="modal-header">
          <h3 class="modal-title">Детали транзакции</h3>
          <button @click="closeDetails" class="btn-close">✕</button>
        </div>
        <div class="modal-body">
          
          <!-- Основная информация -->
          <div class="detail-section">
            <h4 class="section-title">📊 Основная информация</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">ID транзакции:</span>
                <code class="detail-value">{{ selectedTransaction.transaction_id }}</code>
              </div>
              <div class="detail-item">
                <span class="detail-label">Статус:</span>
                <span :class="'badge badge-' + getStatusClass(selectedTransaction.status)">
                  {{ getStatusText(selectedTransaction.status) }}
                </span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Оригинальная сумма:</span>
                <strong class="detail-value">{{ formatAmount(selectedTransaction.original_amount) }} {{ selectedTransaction.currency }}</strong>
              </div>
              <div class="detail-item">
                <span class="detail-label">Оплачено клиентом:</span>
                <strong class="detail-value highlight">{{ formatAmount(selectedTransaction.paid_amount) }} {{ selectedTransaction.currency }}</strong>
              </div>
              <div class="detail-item">
                <span class="detail-label">Общая комиссия банка:</span>
                <span class="detail-value">{{ calculateTotalCommissions() }} {{ selectedTransaction.currency }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Получено чистыми:</span>
                <strong class="detail-value success-text">{{ formatAmount(selectedTransaction.actual_amount_received || 0) }} {{ selectedTransaction.currency }}</strong>
              </div>
              <div v-if="selectedTransaction.description" class="detail-item full-width">
                <span class="detail-label">Описание:</span>
                <span class="detail-value">{{ selectedTransaction.description }}</span>
              </div>
            </div>
          </div>

          <!-- Информация о клиенте -->
          <div class="detail-section" v-if="selectedTransaction.client_ip">
            <h4 class="section-title">👤 Информация о клиенте</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">IP адрес:</span>
                <code class="detail-value">{{ selectedTransaction.client_ip }}</code>
              </div>
              <div class="detail-item">
                <span class="detail-label">Устройство:</span>
                <span class="detail-value">{{ getDeviceIcon(selectedTransaction.client_device) }} {{ selectedTransaction.client_device || '-' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Браузер:</span>
                <span class="detail-value">{{ selectedTransaction.client_browser || '-' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">ОС:</span>
                <span class="detail-value">{{ selectedTransaction.client_os || '-' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Страна:</span>
                <span class="detail-value">{{ selectedTransaction.client_country || '-' }}</span>
              </div>
              <div class="detail-item full-width">
                <span class="detail-label">User Agent:</span>
                <code class="detail-value small">{{ selectedTransaction.client_user_agent || '-' }}</code>
              </div>
            </div>
          </div>

          <!-- ПРЕДУПРЕЖДЕНИЕ о несоответствии -->
          <div class="alert alert-warning" v-if="selectedTransaction.payment_mismatch">
            <h4 style="margin: 0 0 10px 0; color: #f59e0b;">⚠️ НЕСООТВЕТСТВИЕ СПОСОБА ОПЛАТЫ!</h4>
            <p style="margin: 0;">
              <strong>Клиент выбрал один способ оплаты, но оплатил другим!</strong><br>
              Это может привести к недоплате из-за разницы в комиссиях.
            </p>
          </div>

          <!-- Детали от терминала -->
          <div class="detail-section" v-if="selectedTransaction.terminal_order_number || selectedTransaction.terminal_product_type">
            <h4 class="section-title">🖥️ Детали от терминала Kaspi</h4>
            <div class="detail-grid">
              <div class="detail-item" v-if="selectedTransaction.terminal_product_type">
                <span class="detail-label">Тип продукта:</span>
                <span class="detail-value">
                  <strong>{{ selectedTransaction.terminal_product_type }}</strong>
                  ({{ formatProductType(selectedTransaction.terminal_product_type) }})
                </span>
              </div>
              <div class="detail-item" v-if="selectedTransaction.terminal_order_number">
                <span class="detail-label">Номер заказа:</span>
                <code class="detail-value">{{ selectedTransaction.terminal_order_number }}</code>
              </div>
              <div class="detail-item" v-if="selectedTransaction.terminal_transaction_id">
                <span class="detail-label">ID транзакции терминала:</span>
                <code class="detail-value">{{ selectedTransaction.terminal_transaction_id }}</code>
              </div>
              <div class="detail-item full-width" v-if="selectedTransaction.terminal_cheque_status">
                <span class="detail-label">Статус чека:</span>
                <span class="detail-value">{{ selectedTransaction.terminal_cheque_status }}</span>
              </div>
            </div>
          </div>

          <!-- Временная информация -->
          <div class="detail-section">
            <h4 class="section-title">⏰ Временная информация</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">Создан:</span>
                <span class="detail-value">{{ formatDateFull(selectedTransaction.created_at) }}</span>
              </div>
              <div class="detail-item" v-if="selectedTransaction.payment_started_at">
                <span class="detail-label">Открыт клиентом:</span>
                <span class="detail-value">{{ formatDateFull(selectedTransaction.payment_started_at) }}</span>
              </div>
              <div class="detail-item" v-if="selectedTransaction.paid_at">
                <span class="detail-label">Оплачен:</span>
                <span class="detail-value">{{ formatDateFull(selectedTransaction.paid_at) }}</span>
              </div>
              <div class="detail-item" v-if="selectedTransaction.expires_at">
                <span class="detail-label">Истекает:</span>
                <span class="detail-value">{{ formatDateFull(selectedTransaction.expires_at) }}</span>
              </div>
            </div>
          </div>

          <!-- История частичных оплат -->
          <div class="detail-section" v-if="partialPayments.length > 0">
            <h4 class="section-title">💳 История оплат</h4>
            <div class="partial-payments-list">
              <div v-for="(payment, index) in partialPayments" :key="payment.id" class="partial-payment-item" :class="{ 'refunded': payment.is_refunded, 'pending-refund': payment.refund_qr_code && !payment.is_refunded }">
                <div class="payment-number">#{{ index + 1 }}</div>
                <div class="payment-info">
                  <!-- Возврат выполнен -->
                  <div v-if="payment.is_refunded" class="refund-badge">
                    🔄 ВОЗВРАТ ВЫПОЛНЕН
                    <div class="refund-date">{{ formatDate(payment.refunded_at) }}</div>
                  </div>
                  
                  <!-- Возврат инициирован - ждет подтверждения клиента -->
                  <div v-else-if="payment.refund_qr_code" class="refund-pending-badge">
                    ⏳ ОЖИДАЕТ ПОДТВЕРЖДЕНИЯ КЛИЕНТА
                    
                    <!-- Таймер -->
                    <div v-if="refundTimers[payment.id]" class="refund-timer">
                      <span v-if="!refundTimers[payment.id].expired" class="timer-active">
                        ⏱️ QR действителен еще: <strong>{{ formatRefundTimer(refundTimers[payment.id].remaining) }}</strong>
                      </span>
                      <span v-else class="timer-expired">
                        ⚠️ QR код истек! Получите новый QR код.
                      </span>
                    </div>
                    
                    <div class="refund-qr-section" :class="{ 'expired': refundTimers[payment.id]?.expired }">
                      <p class="refund-instructions">
                        <strong>Покажите этот QR код клиенту для подтверждения возврата:</strong>
                      </p>
                      
                      <!-- QR код (генерируется из qr.kaspi.kz) -->
                      <div class="refund-qr-display">
                        <div v-if="refundQrCodes[payment.id]" class="qr-code-wrapper">
                          <img :src="refundQrCodes[payment.id]" alt="QR код возврата" class="qr-image" />
                        </div>
                        <div v-else class="qr-loading">
                          <div class="spinner-small"></div>
                        </div>
                        <p class="qr-label">Клиент должен отсканировать этот QR в приложении Kaspi</p>
                      </div>

                      <!-- Кнопки действий -->
                      <div class="refund-actions">
                        <button 
                          @click="refreshRefundQr(payment)" 
                          class="btn btn-sm btn-warning"
                          :disabled="refunding"
                          title="Получить новый QR код"
                        >
                          <span v-if="refunding">Обновление...</span>
                          <span v-else>🔄 Обновить QR</span>
                        </button>
                        <button 
                          @click="copyRefundLink(getRefundPaymentUrl(payment))" 
                          class="btn btn-sm btn-primary"
                          title="Скопировать ссылку для отправки клиенту"
                        >
                          📋 Скопировать ссылку
                        </button>
                      </div>
                      
                      <!-- Ссылка (скрытая по умолчанию) -->
                      <details class="refund-link-details">
                        <summary>Показать ссылку для отправки</summary>
                        <input 
                          :value="getRefundPaymentUrl(payment)" 
                          readonly 
                          class="refund-link-input"
                        />
                      </details>
                    </div>
                  </div>
                  
                  <div><strong>Сумма:</strong> {{ formatAmount(payment.amount) }} {{ selectedTransaction.currency }}</div>
                  <div v-if="payment.commission_amount > 0">
                    <strong>Комиссия банка ({{ payment.commission_percent }}%):</strong> 
                    {{ formatAmount(payment.commission_amount) }} {{ selectedTransaction.currency }}
                  </div>
                  <div>
                    <strong>Чистыми получено:</strong> 
                    {{ formatAmount(payment.amount - payment.commission_amount) }} {{ selectedTransaction.currency }}
                  </div>
                  <div class="payment-date">{{ formatDate(payment.paid_at) }}</div>
                  
                  <!-- Кнопка возврата -->
                  <div class="payment-actions" v-if="!payment.is_refunded && !payment.refund_qr_code">
                    <button 
                      @click="refundPayment(payment)" 
                      class="btn btn-sm btn-danger"
                      :disabled="refunding"
                    >
                      <span v-if="refunding">Отмена...</span>
                      <span v-else>🔄 Отменить оплату</span>
                    </button>
                  </div>
                </div>
              </div>
              <div class="total-summary">
                <strong>ИТОГО:</strong><br>
                Оплачено клиентом: {{ formatAmount(selectedTransaction.paid_amount) }} ₸<br>
                Комиссии банка: {{ calculateTotalCommissions() }} ₸<br>
                <strong style="color: var(--success-color);">
                  Получено чистыми: {{ formatAmount(selectedTransaction.actual_amount_received) }} ₸
                </strong>
              </div>
            </div>
          </div>

          <!-- Ссылка для оплаты -->
          <div class="detail-section">
            <h4 class="section-title">🔗 Ссылка для оплаты</h4>
            <div class="payment-link-section">
              <input 
                :value="getPaymentUrl(selectedTransaction.transaction_id)" 
                readonly 
                class="link-input"
                ref="linkInput"
              />
              <div class="link-actions">
                <button @click="copyLink(selectedTransaction.transaction_id)" class="btn btn-outline">
                  📋 Копировать
                </button>
                <button @click="openPaymentPage(selectedTransaction.transaction_id)" class="btn btn-primary">
                  👁️ Открыть
                </button>
              </div>
            </div>
          </div>

        </div>
        <div class="modal-footer">
          <button @click="closeDetails" class="btn btn-outline">Закрыть</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import api from '@/services/api'
import QRCode from 'qrcode'

const transactions = ref([])
const sources = ref([])
const partialPayments = ref([])
const loading = ref(false)
const showDetails = ref(false)
const selectedTransaction = ref(null)
const linkInput = ref(null)
const refunding = ref(false)
const refundQrCodes = ref({}) // Хранилище сгенерированных QR кодов
const refundTimers = ref({}) // Таймеры для QR кодов возврата
const filters = ref({
  status: '',
  date_from: '',
  date_to: ''
})

let timerIntervals = {}
let statusCheckIntervals = {}

onMounted(async () => {
  await loadSources()
  await loadTransactions()
})

onUnmounted(() => {
  // Останавливаем все таймеры при выходе со страницы
  Object.values(timerIntervals).forEach(interval => clearInterval(interval))
  Object.values(statusCheckIntervals).forEach(interval => clearInterval(interval))
  timerIntervals = {}
  statusCheckIntervals = {}
})

async function loadSources() {
  try {
    const response = await api.get('/admin/sources')
    sources.value = response.data || []
  } catch (error) {
    console.error('Failed to load sources:', error)
  }
}

async function loadTransactions() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (filters.value.status) params.append('status', filters.value.status)
    if (filters.value.date_from) params.append('date_from', filters.value.date_from)
    if (filters.value.date_to) params.append('date_to', filters.value.date_to)
    
    const response = await api.get(`/admin/transactions?${params.toString()}`)
    transactions.value = response.data || []
  } catch (error) {
    console.error('Failed to load transactions:', error)
  } finally {
    loading.value = false
  }
}

async function viewDetails(transaction) {
  selectedTransaction.value = transaction
  
  // Загружаем историю частичных оплат
  try {
    const response = await api.get(`/admin/transactions/${transaction.id}/partial-payments`)
    partialPayments.value = response.data || []
    
    // Генерируем QR коды для операций с возвратом
    await generateRefundQrCodes()
    
    // Запускаем мониторинг для операций с pending refund
    partialPayments.value.forEach(payment => {
      if (payment.refund_qr_code && !payment.is_refunded) {
        startRefundStatusMonitoring(payment.id)
      }
    })
  } catch (error) {
    console.error('Failed to load partial payments:', error)
    partialPayments.value = []
  }
  
  showDetails.value = true
}

function closeDetails() {
  // Останавливаем все таймеры и проверки
  stopAllRefundMonitoring()
  Object.values(timerIntervals).forEach(interval => clearInterval(interval))
  timerIntervals = {}
  
  // Закрываем модальное окно
  showDetails.value = false
  selectedTransaction.value = null
  partialPayments.value = []
  refundQrCodes.value = {}
  refundTimers.value = {}
}

async function generateRefundQrCodes() {
  // Очищаем старые QR коды
  refundQrCodes.value = {}
  
  // Останавливаем все старые таймеры
  Object.values(timerIntervals).forEach(interval => clearInterval(interval))
  timerIntervals = {}
  
  // Генерируем QR коды для операций с pending refund
  for (const payment of partialPayments.value) {
    if (payment.refund_qr_code && !payment.is_refunded) {
      try {
        const qrDataUrl = await QRCode.toDataURL(payment.refund_qr_code, {
          width: 300,
          margin: 2,
          color: {
            dark: '#000000',
            light: '#FFFFFF'
          }
        })
        refundQrCodes.value[payment.id] = qrDataUrl
        
        // Запускаем таймер для этого QR (3 минуты = 180 секунд)
        startRefundTimer(payment)
      } catch (error) {
        console.error('Failed to generate QR code:', error)
      }
    }
  }
}

function startRefundTimer(payment) {
  // Получаем время создания QR (из refund_details)
  let refundDetails = {}
  try {
    refundDetails = payment.refund_details ? JSON.parse(payment.refund_details) : {}
  } catch (e) {
    console.error('Failed to parse refund_details:', e)
  }
  
  const qrCreatedAt = refundDetails.created_at || new Date().toISOString()
  const qrCreatedTime = new Date(qrCreatedAt).getTime()
  
  // Обновляем таймер каждую секунду
  const updateTimer = () => {
    const now = Date.now()
    const elapsed = Math.floor((now - qrCreatedTime) / 1000)
    const remaining = Math.max(0, 180 - elapsed) // 180 сек = 3 мин
    
    if (!refundTimers.value[payment.id]) {
      refundTimers.value[payment.id] = {}
    }
    
    refundTimers.value[payment.id].remaining = remaining
    refundTimers.value[payment.id].expired = remaining === 0
    
    if (remaining === 0 && timerIntervals[payment.id]) {
      clearInterval(timerIntervals[payment.id])
      delete timerIntervals[payment.id]
    }
  }
  
  // Первое обновление
  updateTimer()
  
  // Запускаем интервал
  timerIntervals[payment.id] = setInterval(updateTimer, 1000)
}

function formatRefundTimer(seconds) {
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
}

async function refreshRefundQr(payment) {
  if (!confirm('Получить новый QR код для возврата?\n\nСтарый QR код станет недействительным.')) {
    return
  }
  
  refunding.value = true
  
  try {
    // Останавливаем старую проверку статуса
    if (statusCheckIntervals[payment.id]) {
      clearInterval(statusCheckIntervals[payment.id])
      delete statusCheckIntervals[payment.id]
    }
    
    // Повторно вызываем API возврата
    const response = await api.post(`/admin/partial-payments/${payment.id}/refund`)
    
    // Перезагружаем данные транзакции
    await viewDetails(selectedTransaction.value)
    
    // Запускаем проверку статуса для нового QR
    if (response.data.requires_confirmation) {
      startRefundStatusMonitoring(payment.id)
    }
    
  } catch (error) {
    const errorMsg = error.response?.data?.message || error.message || 'Неизвестная ошибка'
    alert('❌ Ошибка обновления QR:\n\n' + errorMsg)
  } finally {
    refunding.value = false
  }
}

function calculateTotalCommissions() {
  const total = partialPayments.value.reduce((sum, p) => sum + parseFloat(p.commission_amount || 0), 0)
  return formatAmount(total)
}

function getSourceName(sourceId) {
  const source = sources.value.find(s => s.id === sourceId)
  return source?.name || `#${sourceId}`
}

function formatAmount(amount) {
  return Number(amount).toLocaleString('ru-RU')
}

function formatDate(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function formatDateFull(dateString) {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString('ru-RU', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

function getStatusClass(status) {
  const classes = {
    'paid': 'success',
    'processing': 'warning',
    'pending': 'info',
    'cancelled': 'secondary',
    'failed': 'danger',
    'partially_paid': 'warning'
  }
  return classes[status] || 'secondary'
}

function getStatusText(status) {
  const texts = {
    'paid': 'Оплачено',
    'processing': 'В обработке',
    'pending': 'Ожидание',
    'cancelled': 'Отменено',
    'failed': 'Ошибка',
    'partially_paid': 'Частично оплачено'
  }
  return texts[status] || status
}

function getDeviceIcon(device) {
  const icons = {
    'mobile': '📱',
    'tablet': '📱',
    'desktop': '💻'
  }
  return icons[device] || '📱'
}

function formatProductType(type) {
  const types = {
    'Gold': 'Kaspi Gold - без комиссии',
    'Red': 'Kaspi Red - карта',
    'Credit': 'Kaspi Кредит',
    'Installment': 'Kaspi Рассрочка'
  }
  return types[type] || type
}

function getPaymentUrl(transactionId) {
  const baseUrl = window.location.origin
  return `${baseUrl}/pay/${transactionId}`
}

async function copyLink(transactionId) {
  const url = getPaymentUrl(transactionId)
  try {
    await navigator.clipboard.writeText(url)
    alert('✅ Ссылка скопирована в буфер обмена!')
  } catch (error) {
    // Fallback
    const input = document.createElement('input')
    input.value = url
    document.body.appendChild(input)
    input.select()
    document.execCommand('copy')
    document.body.removeChild(input)
    alert('✅ Ссылка скопирована!')
  }
}

function openPaymentPage(transactionId) {
  const url = getPaymentUrl(transactionId)
  window.open(url, '_blank')
}

async function deleteTransaction(id) {
  if (!confirm('Вы уверены, что хотите удалить эту транзакцию?')) {
    return
  }
  
  try {
    await api.delete(`/admin/transactions/${id}`)
    await loadTransactions()
  } catch (error) {
    alert(error.response?.data?.message || 'Ошибка удаления')
  }
}

async function markAsPaid(transaction) {
  const confirmText = `Отметить транзакцию как ПОЛНОСТЬЮ ОПЛАЧЕННУЮ?\n\nID: ${transaction.transaction_id}\nСумма: ${formatAmount(transaction.amount)} ${transaction.currency}\nОплачено: ${formatAmount(transaction.paid_amount)} ${transaction.currency}\nОстаток: ${formatAmount(transaction.remaining_amount)} ${transaction.currency}\n\nРазница будет списана (округление комиссии)`
  
  if (!confirm(confirmText)) {
    return
  }
  
  try {
    await api.post(`/admin/transactions/${transaction.id}/mark-paid`)
    alert('✅ Транзакция отмечена как полностью оплаченная')
    await loadTransactions()
    if (showDetails.value) {
      showDetails.value = false
    }
  } catch (error) {
    alert(error.response?.data?.message || 'Ошибка')
  }
}

async function refundPayment(payment) {
  if (!confirm(`Вы уверены, что хотите отменить оплату на сумму ${formatAmount(payment.amount)} ₸?\n\nВНИМАНИЕ: Возврат будет выполнен на терминале Kaspi!`)) {
    return
  }
  
  refunding.value = true
  
  try {
    const response = await api.post(`/admin/partial-payments/${payment.id}/refund`)
    
    // Перезагружаем данные транзакции чтобы показать QR ссылку
    await viewDetails(selectedTransaction.value)
    await loadTransactions()
    
    // Запускаем автоматическую проверку статуса возврата
    if (response.data.requires_confirmation) {
      startRefundStatusMonitoring(payment.id)
    } else {
      alert('✅ Возврат успешно выполнен!')
    }
    
  } catch (error) {
    const errorMsg = error.response?.data?.message || error.message || 'Неизвестная ошибка'
    alert('❌ Ошибка возврата:\n\n' + errorMsg)
  } finally {
    refunding.value = false
  }
}

function startRefundStatusMonitoring(paymentId) {
  // Останавливаем предыдущую проверку если была
  if (statusCheckIntervals[paymentId]) {
    clearInterval(statusCheckIntervals[paymentId])
  }
  
  // Проверяем статус каждую секунду
  statusCheckIntervals[paymentId] = setInterval(async () => {
    try {
      const response = await api.get(`/admin/partial-payments/${paymentId}/refund-status`)
      
      console.log('Refund status check:', response.data)
      
      // Если возврат завершен
      if (response.data.is_refunded) {
        clearInterval(statusCheckIntervals[paymentId])
        delete statusCheckIntervals[paymentId]
        
        // Показываем уведомление
        alert('✅ Возврат успешно завершен!\n\nКлиент подтвердил возврат в Kaspi.')
        
        // Обновляем данные
        await viewDetails(selectedTransaction.value)
        await loadTransactions()
      }
    } catch (error) {
      console.error('Refund status check error:', error)
    }
  }, 1000) // Каждую секунду
}

function stopAllRefundMonitoring() {
  // Останавливаем все проверки статуса
  Object.values(statusCheckIntervals).forEach(interval => clearInterval(interval))
  statusCheckIntervals = {}
}

function getRefundPaymentUrl(payment) {
  // Извлекаем payment_url из refund_details
  let refundDetails = {}
  try {
    refundDetails = payment.refund_details ? JSON.parse(payment.refund_details) : {}
  } catch (e) {
    console.error('Failed to parse refund_details:', e)
  }
  
  // Если есть payment_url в details - используем его (pay.kaspi.kz/pay)
  // Иначе генерируем из refund_qr_code
  const paymentUrl = refundDetails.payment_url || payment.refund_qr_code
  
  // Если нужно преобразовать qr.kaspi.kz в pay.kaspi.kz/pay
  if (paymentUrl && paymentUrl.includes('qr.kaspi.kz')) {
    return paymentUrl.replace('qr.kaspi.kz', 'pay.kaspi.kz/pay')
  }
  
  return paymentUrl || payment.refund_qr_code
}

async function copyRefundLink(link) {
  try {
    await navigator.clipboard.writeText(link)
    alert('✅ Ссылка скопирована в буфер обмена!\n\nОтправьте её клиенту (WhatsApp, Telegram).\nПри клике ссылка автоматически откроет приложение Kaspi.')
  } catch (e) {
    // Fallback если clipboard API не работает
    prompt('Скопируйте эту ссылку и отправьте клиенту:', link)
  }
}
</script>

<style scoped>
.filters {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.filters .form-group {
  margin: 0;
  min-width: 200px;
}

code {
  background: var(--bg-color);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-family: monospace;
  font-size: 0.75rem;
}

.text-small {
  font-size: 0.75rem;
  color: var(--text-light);
  margin-top: 0.25rem;
}

.action-buttons {
  display: flex;
  gap: 0.25rem;
}

.client-info {
  font-size: 0.875rem;
}

.client-device {
  font-weight: 600;
  text-transform: capitalize;
}

.client-ip {
  font-family: monospace;
}

.modal-large {
  max-width: 900px;
}

.detail-section {
  margin-bottom: 2rem;
  padding-bottom: 2rem;
  border-bottom: 2px solid var(--border-color);
}

.detail-section:last-child {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}

.section-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--text-color);
  margin-bottom: 1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-item.full-width {
  grid-column: 1 / -1;
}

.detail-label {
  font-size: 0.75rem;
  color: var(--text-light);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.detail-value {
  font-size: 0.875rem;
  color: var(--text-color);
}

.detail-value.highlight {
  font-size: 1.25rem;
  color: var(--primary-color);
}

.detail-value.small {
  font-size: 0.75rem;
  word-break: break-all;
}

.detail-value.success-text {
  color: var(--success-color);
  font-weight: 700;
}

.payment-link-section {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.link-input {
  padding: 1rem;
  border: 2px solid rgba(102, 126, 234, 0.2);
  border-radius: 0.75rem;
  font-size: 0.875rem;
  background: rgba(102, 126, 234, 0.05);
  font-family: monospace;
  width: 100%;
}

.link-actions {
  display: flex;
  gap: 0.5rem;
}

.partial-payments-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.partial-payment-item {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  background: rgba(102, 126, 234, 0.05);
  border-radius: 0.75rem;
  border-left: 4px solid var(--primary-color);
}

.payment-number {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--primary-color);
  min-width: 40px;
}

.payment-info {
  flex: 1;
  font-size: 0.875rem;
  line-height: 1.6;
}

.payment-date {
  color: var(--text-light);
  font-size: 0.75rem;
  margin-top: 0.5rem;
}

.total-summary {
  margin-top: 1rem;
  padding: 1rem;
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
  border-radius: 0.75rem;
  border-left: 4px solid var(--success-color);
  line-height: 1.8;
}

.partial-payment-item.refunded {
  opacity: 0.6;
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, rgba(220, 38, 38, 0.05) 100%);
  border-left-color: var(--danger-color);
}

.partial-payment-item.pending-refund {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(217, 119, 6, 0.08) 100%);
  border-left-color: var(--warning-color);
}

.refund-badge {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  padding: 0.5rem 1rem;
  border-radius: 0.5rem;
  font-weight: 700;
  margin-bottom: 0.75rem;
  text-align: center;
  font-size: 0.875rem;
}

.refund-pending-badge {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  font-weight: 700;
  margin-bottom: 1rem;
  text-align: center;
  font-size: 0.875rem;
}

.refund-date {
  font-size: 0.75rem;
  font-weight: 400;
  opacity: 0.9;
  margin-top: 0.25rem;
}

.refund-timer {
  margin-top: 0.75rem;
  padding: 0.75rem 1rem;
  background: rgba(245, 158, 11, 0.1);
  border-radius: 0.5rem;
  text-align: center;
  font-size: 0.875rem;
  font-weight: 600;
}

.timer-active {
  color: var(--warning-color);
}

.timer-active strong {
  font-size: 1.125rem;
  font-family: monospace;
}

.timer-expired {
  color: var(--danger-color);
  font-weight: 700;
}

.refund-qr-section {
  margin-top: 1rem;
  padding: 1.5rem;
  background: white;
  border-radius: 0.75rem;
  border: 2px solid rgba(245, 158, 11, 0.3);
  transition: all 0.3s ease;
}

.refund-qr-section.expired {
  opacity: 0.5;
  filter: grayscale(1);
  border-color: var(--danger-color);
}

.refund-instructions {
  color: var(--text-color);
  margin: 0 0 1rem 0;
  font-size: 0.875rem;
  text-align: center;
}

.refund-qr-display {
  text-align: center;
  margin-bottom: 1.5rem;
}

.qr-code-wrapper {
  background: white;
  padding: 1rem;
  border-radius: 0.75rem;
  display: inline-block;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.qr-image {
  width: 250px;
  height: 250px;
  display: block;
}

.qr-loading {
  width: 250px;
  height: 250px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
}

.spinner-small {
  width: 40px;
  height: 40px;
  border: 4px solid rgba(245, 158, 11, 0.2);
  border-top-color: var(--warning-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.qr-label {
  margin-top: 1rem;
  font-size: 0.875rem;
  color: var(--text-light);
  font-weight: 600;
}

.refund-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 1.5rem;
  justify-content: center;
}

.btn-warning {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
  border: none;
}

.btn-warning:hover {
  background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
}

.refund-link-details {
  margin-top: 1rem;
  padding: 0.75rem;
  background: rgba(245, 158, 11, 0.05);
  border-radius: 0.5rem;
  cursor: pointer;
}

.refund-link-details summary {
  font-size: 0.75rem;
  color: var(--text-light);
  font-weight: 600;
  list-style: none;
  user-select: none;
}

.refund-link-details summary::-webkit-details-marker {
  display: none;
}

.refund-link-details summary::before {
  content: '▶ ';
  display: inline-block;
  transition: transform 0.2s;
}

.refund-link-details[open] summary::before {
  transform: rotate(90deg);
}

.refund-link-input {
  width: 100%;
  padding: 0.75rem;
  margin-top: 0.5rem;
  border: 2px solid rgba(245, 158, 11, 0.3);
  border-radius: 0.5rem;
  font-size: 0.7rem;
  background: white;
  font-family: monospace;
  color: var(--text-color);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.payment-actions {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(102, 126, 234, 0.1);
}

.btn-sm {
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
}

@media (max-width: 768px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
  
  .link-actions {
    flex-direction: column;
  }
}
</style>
