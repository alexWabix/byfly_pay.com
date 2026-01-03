<template>
  <div class="approve-payment-page">
    <div class="container">
      <div v-if="loading" class="loading">
        <div class="spinner"></div>
        <p>Загрузка...</p>
      </div>

      <div v-else-if="error" class="error-card">
        <div class="error-icon">❌</div>
        <h2>Ошибка</h2>
        <p>{{ error }}</p>
        <router-link to="/login" class="btn btn-primary">Войти в систему</router-link>
      </div>

      <div v-else-if="success" class="success-card">
        <div class="success-icon">✅</div>
        <h2>{{ successMessage }}</h2>
        <p>Платеж успешно {{ actionType === 'approve' ? 'одобрен' : 'отклонен' }}</p>
      </div>

      <div v-else-if="payment" class="payment-card">
        <div class="payment-header">
          <h2>📋 Согласование платежа</h2>
          <span :class="'badge badge-' + getStatusClass(payment.status)">
            {{ getStatusText(payment.status) }}
          </span>
        </div>

        <div class="payment-details">
          <div class="detail-row">
            <span class="label">Название:</span>
            <span class="value">{{ payment.title }}</span>
          </div>

          <div class="detail-row highlight">
            <span class="label">Сумма:</span>
            <span class="value amount">{{ formatAmount(payment.amount) }} {{ payment.currency }}</span>
          </div>

          <div v-if="payment.category_name" class="detail-row">
            <span class="label">Категория:</span>
            <span class="value">{{ payment.category_icon }} {{ payment.category_name }}</span>
          </div>

          <div v-if="payment.recipient" class="detail-row">
            <span class="label">Получатель:</span>
            <span class="value">{{ payment.recipient }}</span>
          </div>

          <div v-if="payment.description" class="detail-row">
            <span class="label">Описание:</span>
            <span class="value">{{ payment.description }}</span>
          </div>

          <div class="detail-row">
            <span class="label">Создан:</span>
            <span class="value">{{ formatDate(payment.created_at) }}</span>
          </div>

          <div class="detail-row">
            <span class="label">Создатель:</span>
            <span class="value">{{ payment.created_by_name || 'Не указан' }}</span>
          </div>

          <div v-if="payment.required_approvals" class="detail-row">
            <span class="label">Подписей требуется:</span>
            <span class="value">{{ payment.approved_count || 0 }} / {{ payment.required_approvals }}</span>
          </div>

          <div v-if="payment.document_url" class="detail-row documents">
            <span class="label">📎 Документы:</span>
            <div class="documents-list">
              <a :href="getDocumentUrl(payment.document_url)" target="_blank" class="document-link">
                📄 {{ payment.document_filename || 'Документ' }}
              </a>
            </div>
          </div>
        </div>

        <div v-if="approval && approval.status === 'pending'" class="action-buttons">
          <button @click="showRejectDialog = true" class="btn btn-danger">
            ❌ Отклонить
          </button>
          <button @click="approvePayment" class="btn btn-success" :disabled="processing">
            <span v-if="processing">Обработка...</span>
            <span v-else>✅ Одобрить</span>
          </button>
        </div>

        <div v-else-if="approval" class="approval-status">
          <div v-if="approval.status === 'approved'" class="status-success">
            ✅ Вы уже одобрили этот платеж {{ formatDate(approval.approved_at) }}
          </div>
          <div v-else-if="approval.status === 'rejected'" class="status-danger">
            ❌ Вы отклонили этот платеж {{ formatDate(approval.rejected_at) }}
            <p v-if="approval.comment">Причина: {{ approval.comment }}</p>
          </div>
        </div>
      </div>

      <!-- Модальное окно отклонения -->
      <div v-if="showRejectDialog" class="modal-overlay" @click.self="showRejectDialog = false">
        <div class="modal">
          <div class="modal-header">
            <h3>Отклонение платежа</h3>
            <button @click="showRejectDialog = false" class="btn-close">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Причина отклонения:</label>
              <textarea v-model="rejectComment" class="form-control" rows="4" placeholder="Укажите причину отклонения..." required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button @click="showRejectDialog = false" class="btn btn-outline">Отмена</button>
            <button @click="rejectPayment" class="btn btn-danger" :disabled="!rejectComment || processing">
              <span v-if="processing">Отклонение...</span>
              <span v-else">❌ Отклонить платеж</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const loading = ref(true)
const error = ref(null)
const success = ref(false)
const successMessage = ref('')
const actionType = ref('')
const processing = ref(false)
const payment = ref(null)
const approval = ref(null)
const showRejectDialog = ref(false)
const rejectComment = ref('')

onMounted(async () => {
  
  const token = route.query.token
  
  if (!token) {
    error.value = 'Неверная ссылка'
    loading.value = false
    return
  }
  
  await loadPayment(token)
})

async function loadPayment(token) {
  try {
    const response = await axios.get(`/api/approvals/payment-by-token/${token}`)
    
    // Проверяем структуру ответа
    if (response.data && response.data.data) {
      // Если данные в response.data.data (стандартный формат API)
      payment.value = response.data.data.payment
      approval.value = response.data.data.approval
    } else {
      // Если данные напрямую в response.data
      payment.value = response.data.payment
      approval.value = response.data.approval
    }
    
    loading.value = false
  } catch (err) {
    error.value = err.response?.data?.message || 'Платеж не найден или ссылка истекла'
    loading.value = false
  }
}

async function approvePayment() {
  if (!confirm('Вы уверены что хотите одобрить этот платеж?')) return
  
  processing.value = true
  const token = route.query.token
  
  try {
    await axios.post(`/api/approvals/approve-by-token/${token}`)
    success.value = true
    successMessage.value = 'Платеж одобрен'
    actionType.value = 'approve'
  } catch (err) {
    alert(err.response?.data?.message || 'Ошибка одобрения')
  } finally {
    processing.value = false
  }
}

async function rejectPayment() {
  if (!rejectComment.value) {
    alert('Укажите причину отклонения')
    return
  }
  
  processing.value = true
  const token = route.query.token
  
  try {
    await axios.post(`/api/approvals/reject-by-token/${token}`, {
      comment: rejectComment.value
    })
    success.value = true
    successMessage.value = 'Платеж отклонен'
    actionType.value = 'reject'
    showRejectDialog.value = false
  } catch (err) {
    alert(err.response?.data?.message || 'Ошибка отклонения')
  } finally {
    processing.value = false
  }
}

function formatAmount(value) {
  return Number(value).toLocaleString('ru-RU')
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleString('ru-RU')
}

function getStatusClass(status) {
  const classes = { pending: 'warning', approved: 'success', rejected: 'danger', paid: 'info' }
  return classes[status] || 'secondary'
}

function getStatusText(status) {
  const texts = { pending: 'На согласовании', approved: 'Одобрено', rejected: 'Отклонено', paid: 'Оплачено' }
  return texts[status] || status
}

function getDocumentUrl(path) {
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path
  }
  return `${window.location.origin}/${path}`
}
</script>

<style scoped>
.approve-payment-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.container {
  max-width: 600px;
  width: 100%;
}

.loading {
  text-align: center;
  color: white;
  font-size: 1.25rem;
}

.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error-card, .success-card, .payment-card {
  background: white;
  border-radius: 1rem;
  padding: 2rem;
  box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.error-icon, .success-icon {
  font-size: 4rem;
  text-align: center;
  margin-bottom: 1rem;
}

.error-card h2, .success-card h2 {
  text-align: center;
  margin: 0 0 1rem 0;
}

.error-card p, .success-card p {
  text-align: center;
  margin-bottom: 1.5rem;
}

.payment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 1rem;
  border-bottom: 2px solid #eee;
  margin-bottom: 1.5rem;
}

.payment-header h2 {
  margin: 0;
  font-size: 1.5rem;
}

.payment-details {
  margin-bottom: 2rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem 0;
  border-bottom: 1px solid #f0f0f0;
}

.detail-row.highlight {
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
  padding: 1rem;
  border-radius: 0.5rem;
  margin: 0.5rem 0;
  border-bottom: none;
}

.detail-row .label {
  color: #666;
  font-weight: 600;
}

.detail-row .value {
  color: #333;
  text-align: right;
}

.detail-row .value.amount {
  font-size: 1.5rem;
  font-weight: 700;
  color: #667eea;
}

.detail-row.documents {
  flex-direction: column;
  gap: 0.5rem;
}

.documents-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.document-link {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 1rem;
  background: #f0f0f0;
  border-radius: 0.5rem;
  text-decoration: none;
  color: #667eea;
  font-weight: 600;
  transition: all 0.2s;
}

.document-link:hover {
  background: #e0e0e0;
  transform: translateX(4px);
}

.action-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.approval-status {
  padding: 1rem;
  border-radius: 0.5rem;
  text-align: center;
  font-weight: 600;
}

.status-success {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
  padding: 1rem;
  border-radius: 0.5rem;
}

.status-danger {
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  padding: 1rem;
  border-radius: 0.5rem;
}
</style>

