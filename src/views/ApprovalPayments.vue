<template>
  <div class="approvals-page">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">📋 Согласование платежей</h3>
        <div class="header-actions">
          <router-link to="/create-outgoing-payment" class="btn btn-primary">
            ➕ Создать платеж
          </router-link>
        </div>
      </div>

      <!-- Фильтры -->
      <div class="filters-panel">
        <div class="filter-tabs">
          <button @click="setFilter('all')" :class="['filter-tab', { active: currentFilter === 'all' }]">
            Все ({{ stats.all }})
          </button>
          <button @click="setFilter('pending')" :class="['filter-tab', { active: currentFilter === 'pending' }]">
            Жду подписи ({{ stats.pending }})
          </button>
          <button @click="setFilter('created')" :class="['filter-tab', { active: currentFilter === 'created' }]">
            Мои созданные ({{ stats.created }})
          </button>
          <button @click="setFilter('approved')" :class="['filter-tab', { active: currentFilter === 'approved' }]">
            Одобренные ({{ stats.approved }})
          </button>
          <button @click="setFilter('paid')" :class="['filter-tab', { active: currentFilter === 'paid' }]">
            Оплаченные ({{ stats.paid }})
          </button>
        </div>

        <!-- Дополнительные фильтры -->
        <div class="advanced-filters">
          <input v-model="filters.search" type="text" class="form-control" placeholder="🔍 Поиск..." />
          <select v-model="filters.categoryId" class="form-control">
            <option value="">Все категории</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
              {{ cat.icon_emoji }} {{ cat.name }}
            </option>
          </select>
          <select v-model="filters.createdBy" class="form-control">
            <option value="">Все создатели</option>
            <option v-for="admin in admins" :key="admin.id" :value="admin.id">
              {{ admin.name || admin.phone }}
            </option>
          </select>
          <select v-model="filters.approver" class="form-control">
            <option value="">Все подписанты</option>
            <option v-for="admin in admins" :key="admin.id" :value="admin.id">
              {{ admin.name || admin.phone }}
            </option>
          </select>
          <input v-model="filters.dateFrom" type="date" class="form-control" />
          <input v-model="filters.dateTo" type="date" class="form-control" />
          <button @click="applyFilters" class="btn btn-outline">Применить</button>
        </div>

        <!-- Статистика по фильтрам -->
        <div v-if="hasActiveFilters" class="filter-stats">
          <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
              <div class="stat-label">Всего операций</div>
              <div class="stat-value">{{ filterStats.total }}</div>
            </div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
              <div class="stat-label">Общая сумма</div>
              <div class="stat-value">{{ formatAmount(filterStats.totalAmount) }} KZT</div>
            </div>
          </div>
          
          <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
              <div class="stat-label">Одобрено</div>
              <div class="stat-value">{{ filterStats.approved }}</div>
            </div>
          </div>
          
          <div class="stat-card info">
            <div class="stat-icon">💳</div>
            <div class="stat-content">
              <div class="stat-label">Оплачено</div>
              <div class="stat-value">{{ filterStats.paid }}</div>
            </div>
          </div>
          
          <div class="stat-card warning">
            <div class="stat-icon">⏳</div>
            <div class="stat-content">
              <div class="stat-label">На согласовании</div>
              <div class="stat-value">{{ filterStats.pending }}</div>
            </div>
          </div>
          
          <div class="stat-card danger">
            <div class="stat-icon">❌</div>
            <div class="stat-content">
              <div class="stat-label">Отклонено</div>
              <div class="stat-value">{{ filterStats.rejected }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Массовые действия -->
      <div v-if="selected.length > 0" class="bulk-actions">
        <button @click="bulkApprove" class="btn btn-success">
          ✅ Одобрить выбранные ({{ selected.length }})
        </button>
        <button @click="selected = []" class="btn btn-outline">
          Отменить выбор
        </button>
      </div>

      <!-- Список платежей -->
      <div class="payments-list">
        <div v-for="payment in paginatedPayments" :key="payment.id" class="payment-card" @click="viewDetails(payment)">
          <input v-if="currentFilter === 'pending'" v-model="selected" type="checkbox" :value="payment.id" @click.stop />
          
          <div class="payment-icon" :style="{ background: payment.category_color || '#667eea' }">
            {{ payment.category_icon || '💰' }}
          </div>
          
          <div class="payment-content">
            <div class="payment-header">
              <h4>{{ payment.title }}</h4>
              <div class="payment-header-right">
                <span :class="'badge badge-' + getStatusClass(payment.status)">
                  {{ getStatusText(payment.status) }}
                </span>
                <button @click.stop="viewDetails(payment)" class="btn btn-sm btn-icon">👁️</button>
              </div>
            </div>
            
            <p class="payment-desc">{{ payment.description || 'Нет описания' }}</p>
            
            <div class="payment-meta">
              <span class="amount">{{ formatAmount(payment.amount) }} {{ payment.currency }}</span>
              <span>•</span>
              <span>{{ payment.category_name || 'Без категории' }}</span>
              <span>•</span>
              <span>{{ formatDate(payment.created_at) }}</span>
              <span v-if="payment.approved_count">•</span>
              <span v-if="payment.approved_count">{{ payment.approved_count }}/{{ payment.required_approvals }} подписей</span>
            </div>
          </div>
          
          <div class="payment-actions" @click.stop>
            <button v-if="currentFilter === 'pending'" @click="approve(payment.id)" class="btn btn-sm btn-success" title="Одобрить">✅</button>
            <button v-if="currentFilter === 'pending'" @click="reject(payment.id)" class="btn btn-sm btn-danger" title="Отклонить">❌</button>
            <button v-if="payment.status === 'pending'" @click="resendSms(payment.id)" class="btn btn-sm btn-outline" title="Отправить SMS еще раз">
              📱
            </button>
          </div>
        </div>
      </div>

      <!-- Пагинация -->
      <div class="pagination">
        <button @click="currentPage--" :disabled="currentPage === 1" class="btn btn-outline">← Назад</button>
        <span class="page-info">Страница {{ currentPage }} из {{ totalPages }} (Всего: {{ filteredPayments.length }})</span>
        <button @click="currentPage++" :disabled="currentPage >= totalPages" class="btn btn-outline">Вперед →</button>
      </div>
    </div>

    <!-- Модальное окно деталей -->
    <div v-if="showDetails && selectedPayment" class="modal-overlay" @click.self="showDetails = false">
      <div class="modal modal-large">
        <div class="modal-header">
          <h3>Детали платежа</h3>
          <button @click="showDetails = false" class="btn-close">✕</button>
        </div>
        <div class="modal-body">
          <div class="detail-grid">
            <div class="detail-item">
              <span class="label">Название:</span>
              <span class="value">{{ selectedPayment.title }}</span>
            </div>
            <div class="detail-item">
              <span class="label">Сумма:</span>
              <span class="value amount">{{ formatAmount(selectedPayment.amount) }} {{ selectedPayment.currency }}</span>
            </div>
            <div class="detail-item">
              <span class="label">Категория:</span>
              <span class="value">{{ selectedPayment.category_name || 'Без категории' }}</span>
            </div>
            <div class="detail-item">
              <span class="label">Статус:</span>
              <span :class="'badge badge-' + getStatusClass(selectedPayment.status)">
                {{ getStatusText(selectedPayment.status) }}
              </span>
            </div>
            <div class="detail-item">
              <span class="label">Создан:</span>
              <span class="value">{{ formatDate(selectedPayment.created_at) }}</span>
            </div>
            <div class="detail-item">
              <span class="label">Создатель:</span>
              <span class="value">{{ selectedPayment.created_by_name || 'Не указан' }}</span>
            </div>
            
            <div v-if="selectedPayment.approvals && selectedPayment.approvals.length > 0" class="detail-item full">
              <span class="label">Подписи ({{ selectedPayment.approved_count || 0 }}/{{ selectedPayment.required_approvals || 2 }}):</span>
              <div class="approvals-list">
                <div v-for="(approval, index) in selectedPayment.approvals" :key="index" 
                     :class="['approval-badge', approval.status === 'approved' ? 'success' : approval.status === 'rejected' ? 'danger' : 'warning']">
                  <span v-if="approval.status === 'approved'">✅</span>
                  <span v-else-if="approval.status === 'rejected'">❌</span>
                  <span v-else>⏳</span>
                  {{ approval.admin_name || approval.admin_phone || 'Подписант' }}
                  <span v-if="approval.approved_at"> - {{ formatDate(approval.approved_at) }}</span>
                  <span v-else-if="approval.status === 'pending'"> - Ожидание</span>
                </div>
              </div>
            </div>
            
            <div v-if="selectedPayment.description" class="detail-item full">
              <span class="label">Описание:</span>
              <span class="value">{{ selectedPayment.description }}</span>
            </div>

            <div v-if="selectedPayment.document_url" class="detail-item full">
              <span class="label">📎 Прикрепленные документы:</span>
              <div class="documents-list">
                <a :href="getDocumentUrl(selectedPayment.document_url)" target="_blank" class="document-link">
                  📄 {{ selectedPayment.document_filename || 'Документ 1' }}
                </a>
              </div>
            </div>

            <div v-if="selectedPayment.status === 'paid' && selectedPayment.paid_document_url" class="detail-item full">
              <span class="label">📎 Подтверждение оплаты:</span>
              <div class="documents-list">
                <a :href="getDocumentUrl(selectedPayment.paid_document_url)" target="_blank" class="document-link paid">
                  ✅ {{ selectedPayment.paid_document_filename || 'Подтверждение оплаты' }}
                </a>
              </div>
            </div>

            <div v-if="additionalDocuments.length > 0" class="detail-item full">
              <span class="label">📎 Дополнительные документы ({{ additionalDocuments.length }}):</span>
              <div class="documents-list">
                <div v-for="doc in additionalDocuments" :key="doc.id" class="document-item">
                  <a :href="getDocumentUrl(doc.document_url)" target="_blank" class="document-link">
                    📄 {{ doc.document_filename }}
                  </a>
                  <button 
                    v-if="canEditPayment" 
                    @click="deleteDocument(doc.id)" 
                    class="btn-delete-doc"
                    title="Удалить документ">
                    🗑️
                  </button>
                </div>
              </div>
            </div>

            <div v-if="canEditPayment" class="detail-item full">
              <span class="label">➕ Добавить документ:</span>
              <input 
                type="file" 
                @change="uploadAdditionalDocument" 
                class="form-control" 
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" />
              <small class="form-hint">Можно загрузить любые дополнительные документы (чеки, акты, договоры и т.д.)</small>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showDetails = false" class="btn btn-outline">Закрыть</button>
          <button 
            v-if="canEditPayment" 
            @click="deletePayment(selectedPayment.id)" 
            class="btn btn-danger">
            🗑️ Удалить платеж
          </button>
          <button 
            v-if="selectedPayment.status === 'approved' && canEditPayment && !selectedPayment.paid_at" 
            @click="openMarkPaidDialog" 
            class="btn btn-success">
            💳 Отметить как оплаченный
          </button>
        </div>
      </div>
    </div>

    <!-- Модальное окно отметки как оплаченный -->
    <div v-if="showMarkPaidDialog" class="modal-overlay" @click.self="showMarkPaidDialog = false">
      <div class="modal">
        <div class="modal-header">
          <h3>💳 Отметить как оплаченный</h3>
          <button @click="showMarkPaidDialog = false" class="btn-close">✕</button>
        </div>
        <div class="modal-body">
          <p><strong>{{ selectedPayment.title }}</strong></p>
          <p>Сумма: <strong>{{ formatAmount(selectedPayment.amount) }} {{ selectedPayment.currency }}</strong></p>
          
          <div class="form-group">
            <label class="form-label">🏦 Банк для списания *</label>
            <select v-model="selectedBankId" class="form-control" required>
              <option value="">Выберите банк</option>
              <option v-for="bank in activeBanks" :key="bank.id" :value="bank.id">
                {{ bank.bank_icon }} {{ bank.name }} - {{ formatAmount(bank.balance) }} {{ bank.currency }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">📎 Документ подтверждения оплаты</label>
            <input 
              type="file" 
              @change="uploadPaidDocument" 
              ref="paidDocInput"
              class="form-control" 
              accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" />
            <small v-if="paidDocumentFilename" class="form-hint success">✅ {{ paidDocumentFilename }}</small>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showMarkPaidDialog = false" class="btn btn-outline">Отмена</button>
          <button 
            @click="markAsPaid" 
            class="btn btn-success" 
            :disabled="!paidDocumentUrl || !selectedBankId || markingPaid">
            <span v-if="markingPaid">Отмечаю...</span>
            <span v-else>✅ Подтвердить оплату</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const allPayments = ref([]) // Все платежи для статистики
const payments = ref([]) // Отфильтрованные платежи для отображения
const categories = ref([])
const admins = ref([])
const activeBanks = ref([])
const selected = ref([])
const selectedBankId = ref('')
const currentFilter = ref('all')
const currentPage = ref(1)
const perPage = ref(20)
const showDetails = ref(false)
const showMarkPaidDialog = ref(false)
const selectedPayment = ref(null)
const additionalDocuments = ref([])
const paidDocumentUrl = ref(null)
const paidDocumentFilename = ref(null)
const markingPaid = ref(false)
const paidDocInput = ref(null)
const filters = ref({
  search: '',
  categoryId: '',
  createdBy: '',
  approver: '',
  dateFrom: '',
  dateTo: ''
})

const stats = ref({
  all: 0,
  pending: 0,
  created: 0,
  approved: 0,
  paid: 0
})

// Получаем текущего админа из localStorage
const currentAdminId = computed(() => {
  try {
    const adminData = localStorage.getItem('admin')
    if (adminData && adminData !== 'null') {
      const admin = JSON.parse(adminData)
      return admin.id
    }
  } catch (e) {
    console.error('Error parsing admin:', e)
  }
  return null
})

// Проверка что текущий пользователь - создатель платежа
const canEditPayment = computed(() => {
  if (!selectedPayment.value) return false
  
  // Проверяем через is_created_by_me ИЛИ сравниваем ID
  return selectedPayment.value.is_created_by_me || 
         (selectedPayment.value.created_by_admin_id == currentAdminId.value)
})

const filteredPayments = computed(() => {
  let result = payments.value

  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    result = result.filter(p => 
      p.title.toLowerCase().includes(search) ||
      (p.description && p.description.toLowerCase().includes(search))
    )
  }

  if (filters.value.categoryId) {
    result = result.filter(p => p.category_id == filters.value.categoryId)
  }

  if (filters.value.createdBy) {
    result = result.filter(p => p.created_by_admin_id == filters.value.createdBy)
  }

  if (filters.value.approver) {
    result = result.filter(p => {
      if (!p.approvals) return false
      return p.approvals.some(a => a.admin_id == filters.value.approver)
    })
  }

  if (filters.value.dateFrom) {
    result = result.filter(p => new Date(p.created_at) >= new Date(filters.value.dateFrom))
  }

  if (filters.value.dateTo) {
    result = result.filter(p => new Date(p.created_at) <= new Date(filters.value.dateTo))
  }

  return result
})

const totalPages = computed(() => Math.ceil(filteredPayments.value.length / perPage.value))

const paginatedPayments = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredPayments.value.slice(start, start + perPage.value)
})

// Проверка активных фильтров
const hasActiveFilters = computed(() => {
  return filters.value.search || 
         filters.value.categoryId || 
         filters.value.createdBy || 
         filters.value.approver || 
         filters.value.dateFrom || 
         filters.value.dateTo
})

// Статистика по отфильтрованным данным
const filterStats = computed(() => {
  const filtered = filteredPayments.value
  
  return {
    total: filtered.length,
    totalAmount: filtered.reduce((sum, p) => sum + parseFloat(p.amount || 0), 0),
    approved: filtered.filter(p => p.status === 'approved').length,
    paid: filtered.filter(p => p.status === 'paid').length,
    pending: filtered.filter(p => p.status === 'pending').length,
    rejected: filtered.filter(p => p.status === 'rejected').length
  }
})

onMounted(() => {
  loadCategories()
  loadAdmins()
  loadBanks()
  loadAllPayments()
})

async function loadBanks() {
  try {
    const response = await api.get('/banks')
    activeBanks.value = (response.data || []).filter(b => b.is_active)
  } catch (error) {
    console.error('Ошибка загрузки банков:', error)
  }
}

async function loadCategories() {
  try {
    const response = await api.get('/approvals/categories')
    categories.value = response.data || []
  } catch (error) {
    console.error('Ошибка загрузки категорий:', error)
  }
}

async function loadAdmins() {
  try {
    const response = await api.get('/admin/admins')
    admins.value = response.data || []
  } catch (error) {
    console.error('Ошибка загрузки администраторов:', error)
  }
}

async function loadAllPayments() {
  try {
    // Загружаем ВСЕ платежи для статистики
    const response = await api.get('/approvals/payments')
    allPayments.value = response.data || []
    
    // Обновляем статистику
    updateStats()
    
    // Применяем фильтр для текущей вкладки
    setFilter(currentFilter.value)
  } catch (error) {
    console.error(error)
  }
}

function setFilter(filter) {
  currentFilter.value = filter
  currentPage.value = 1
  
  // Фильтруем из allPayments на основе выбранного таба
  if (filter === 'all') {
    payments.value = allPayments.value
  } else if (filter === 'pending') {
    payments.value = allPayments.value.filter(p => p.my_status === 'pending')
  } else if (filter === 'created') {
    // Нужно знать текущего пользователя - получим из store
    payments.value = allPayments.value.filter(p => p.is_created_by_me)
  } else if (filter === 'approved') {
    payments.value = allPayments.value.filter(p => p.status === 'approved')
  } else if (filter === 'paid') {
    payments.value = allPayments.value.filter(p => p.status === 'paid')
  }
}

function updateStats() {
  // Считаем статистику от ВСЕХ платежей
  stats.value.all = allPayments.value.length
  stats.value.pending = allPayments.value.filter(p => p.my_status === 'pending').length
  stats.value.created = allPayments.value.filter(p => p.is_created_by_me).length
  stats.value.approved = allPayments.value.filter(p => p.status === 'approved').length
  stats.value.paid = allPayments.value.filter(p => p.status === 'paid').length
}

function applyFilters() {
  currentPage.value = 1
  // Фильтрация происходит автоматически через computed filteredPayments
}

async function viewDetails(payment) {
  selectedPayment.value = payment
  showDetails.value = true
  
  // Загружаем дополнительные документы
  await loadAdditionalDocuments(payment.id)
}

async function loadAdditionalDocuments(paymentId) {
  try {
    const response = await api.get(`/approvals/payments/${paymentId}/documents`)
    additionalDocuments.value = response.data || []
  } catch (error) {
    console.error('Ошибка загрузки документов:', error)
    additionalDocuments.value = []
  }
}

async function approve(id) {
  try {
    await api.post(`/approvals/payments/${id}/approve`)
    alert('✅ Одобрено')
    loadAllPayments()
  } catch (error) {
    alert('Ошибка')
  }
}

async function reject(id) {
  const comment = prompt('Причина:')
  try {
    await api.post(`/approvals/payments/${id}/reject`, { comment })
    alert('❌ Отклонено')
    loadAllPayments()
  } catch (error) {
    alert('Ошибка')
  }
}

async function bulkApprove() {
  try {
    await api.post('/approvals/bulk-approve', { payment_ids: selected.value })
    alert('✅ Одобрено!')
    selected.value = []
    loadAllPayments()
  } catch (error) {
    alert('Ошибка')
  }
}

async function resendSms(id) {
  if (!confirm('Отправить SMS всем подписантам повторно?')) return
  
  try {
    const response = await api.post(`/approvals/payments/${id}/resend-sms`)
    alert(`✅ SMS отправлено: ${response.data.sent} подписантам`)
  } catch (error) {
    alert('❌ Ошибка отправки SMS')
  }
}

function openMarkPaidDialog() {
  paidDocumentUrl.value = null
  paidDocumentFilename.value = null
  selectedBankId.value = ''
  showMarkPaidDialog.value = true
}

async function uploadPaidDocument(event) {
  const file = event.target.files[0]
  if (!file) return
  
  const formData = new FormData()
  formData.append('file', file)
  
  try {
    const response = await api.post('/upload/document', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    paidDocumentUrl.value = response.data.url
    paidDocumentFilename.value = response.data.filename
    alert('✅ Документ загружен успешно!')
  } catch (error) {
    console.error(error)
    alert('❌ Ошибка загрузки документа')
  }
}

async function markAsPaid() {
  if (!paidDocumentUrl.value) {
    alert('Загрузите документ подтверждения оплаты')
    return
  }
  
  if (!selectedBankId.value) {
    alert('Выберите банк для списания')
    return
  }
  
  if (!confirm('Подтвердить что платеж оплачен?')) return
  
  markingPaid.value = true
  
  try {
    await api.post(`/approvals/payments/${selectedPayment.value.id}/mark-paid`, {
      document_url: paidDocumentUrl.value,
      document_filename: paidDocumentFilename.value,
      bank_id: selectedBankId.value
    })
    
    alert('✅ Платеж отмечен как оплаченный! Баланс банка обновлен.')
    showMarkPaidDialog.value = false
    showDetails.value = false
    loadAllPayments()
  } catch (error) {
    alert(error.response?.data?.message || '❌ Ошибка')
  } finally {
    markingPaid.value = false
  }
}

async function uploadAdditionalDocument(event) {
  const file = event.target.files[0]
  if (!file) return
  
  const formData = new FormData()
  formData.append('file', file)
  
  try {
    const response = await api.post('/upload/document', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    // Сохраняем документ в БД
    await api.post(`/approvals/payments/${selectedPayment.value.id}/documents`, {
      document_url: response.data.url,
      document_filename: response.data.filename,
      document_type: 'additional',
      file_size: response.data.size
    })
    
    alert('✅ Документ добавлен!')
    event.target.value = '' // Очистить input
    await loadAdditionalDocuments(selectedPayment.value.id)
  } catch (error) {
    console.error(error)
    alert('❌ Ошибка загрузки документа')
  }
}

async function deleteDocument(docId) {
  if (!confirm('Удалить этот документ?')) return
  
  try {
    await api.delete(`/approvals/payments/${selectedPayment.value.id}/documents/${docId}`)
    alert('✅ Документ удален')
    await loadAdditionalDocuments(selectedPayment.value.id)
  } catch (error) {
    alert('❌ Ошибка удаления документа')
  }
}

async function deletePayment(id) {
  const payment = selectedPayment.value
  
  const confirmText = `⚠️ ВНИМАНИЕ! Вы собираетесь ПОЛНОСТЬЮ УДАЛИТЬ платеж:\n\n"${payment.title}"\nСумма: ${formatAmount(payment.amount)} ${payment.currency}\n\nПЛАТЕЖ БУДЕТ УДАЛЕН НАВСЕГДА!\nВсе документы и подписи также будут удалены.\n\nВы уверены?`
  
  if (!confirm(confirmText)) return
  
  // Двойное подтверждение для оплаченных
  if (payment.status === 'paid') {
    if (!confirm('⚠️⚠️⚠️ ВНИМАНИЕ!\n\nПлатеж УЖЕ ОПЛАЧЕН!\n\nВы ДЕЙСТВИТЕЛЬНО хотите его удалить?')) {
      return
    }
  }
  
  try {
    await api.delete(`/approvals/payments/${id}`)
    alert('✅ Платеж полностью удален')
    showDetails.value = false
    loadAllPayments()
  } catch (error) {
    alert(error.response?.data?.message || '❌ Ошибка удаления')
  }
}

function formatAmount(value) {
  return Number(value).toLocaleString('ru-RU')
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('ru-RU')
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
  // Если путь уже полный URL, возвращаем как есть
  if (path.startsWith('http://') || path.startsWith('https://')) {
    return path
  }
  // Иначе добавляем базовый URL
  return `${window.location.origin}/${path}`
}
</script>

<style scoped>
.filters-panel {
  padding: 1.5rem;
  background: var(--bg-color);
  border-bottom: 2px solid var(--border-color);
}

.filter-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.filter-tab {
  padding: 0.75rem 1.5rem;
  border: 2px solid transparent;
  background: white;
  border-radius: 0.75rem;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
}

.filter-tab:hover {
  background: rgba(102, 126, 234, 0.1);
}

.filter-tab.active {
  background: var(--bg-gradient);
  color: white;
  border-color: var(--primary-color);
}

.advanced-filters {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1fr 1fr 0.8fr 0.8fr auto;
  gap: 0.5rem;
}

.bulk-actions {
  padding: 1rem;
  background: rgba(16, 185, 129, 0.1);
  display: flex;
  gap: 0.5rem;
  border-bottom: 2px solid var(--success-color);
}

.payments-list {
  padding: 1.5rem;
}

.payment-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  background: white;
  border-radius: 1rem;
  margin-bottom: 1rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  cursor: pointer;
  transition: all 0.2s;
}

.payment-card:hover {
  box-shadow: 0 4px 16px rgba(102, 126, 234, 0.2);
  transform: translateY(-2px);
}

.payment-icon {
  width: 60px;
  height: 60px;
  border-radius: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  color: white;
  flex-shrink: 0;
}

.payment-content {
  flex: 1;
}

.payment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.payment-header h4 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  flex: 1;
}

.payment-header-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.btn-icon {
  width: 36px;
  height: 36px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.5rem;
  font-size: 1.25rem;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 2px solid var(--border-color);
}

.header-actions {
  margin-left: auto;
}

.payment-desc {
  margin: 0 0 0.75rem 0;
  color: var(--text-light);
  font-size: 0.875rem;
}

.payment-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  color: var(--text-light);
}

.payment-meta .amount {
  font-weight: 700;
  font-size: 0.875rem;
  color: var(--primary-color);
}

.payment-actions {
  display: flex;
  gap: 0.5rem;
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  border-top: 2px solid var(--border-color);
}

.page-info {
  font-weight: 600;
  color: var(--text-color);
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

.detail-item.full {
  grid-column: 1 / -1;
}

.detail-item .label {
  font-size: 0.75rem;
  color: var(--text-light);
  text-transform: uppercase;
  font-weight: 700;
}

.detail-item .value {
  font-size: 1rem;
  color: var(--text-color);
}

.detail-item .value.amount {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--primary-color);
}

.approvals-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.approval-badge {
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
}

.approval-badge.success {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
  border-left: 4px solid var(--success-color);
  color: var(--success-color);
}

.approval-badge.danger {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
  border-left: 4px solid var(--danger-color);
  color: var(--danger-color);
}

.approval-badge.warning {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
  border-left: 4px solid var(--warning-color);
  color: var(--warning-color);
}

.documents-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.document-link {
  display: inline-flex;
  align-items: center;
  padding: 0.75rem 1rem;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(102, 126, 234, 0.1) 100%);
  border-left: 4px solid var(--primary-color);
  border-radius: 0.5rem;
  color: var(--primary-color);
  text-decoration: none;
  font-weight: 600;
  transition: all 0.2s;
}

.document-link:hover {
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(102, 126, 234, 0.15) 100%);
  transform: translateX(4px);
}

.document-link.paid {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(16, 185, 129, 0.1) 100%);
  border-left-color: var(--success-color);
  color: var(--success-color);
}

.document-link.paid:hover {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.15) 100%);
}

.filter-stats {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 0.5rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--border-color);
}

@media (max-width: 1400px) {
  .filter-stats {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 768px) {
  .filter-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 0.75rem;
  background: white;
  border-radius: 0.5rem;
  border-left: 3px solid var(--primary-color);
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  transition: all 0.2s;
}

.stat-card:hover {
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  transform: translateY(-1px);
}

.stat-card.success {
  border-left-color: var(--success-color);
}

.stat-card.info {
  border-left-color: #3b82f6;
}

.stat-card.warning {
  border-left-color: var(--warning-color);
}

.stat-card.danger {
  border-left-color: var(--danger-color);
}

.stat-icon {
  font-size: 1.5rem;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(102, 126, 234, 0.05) 100%);
  border-radius: 0.375rem;
  flex-shrink: 0;
}

.stat-card.success .stat-icon {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
}

.stat-card.info .stat-icon {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
}

.stat-card.warning .stat-icon {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(245, 158, 11, 0.05) 100%);
}

.stat-card.danger .stat-icon {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
}

.stat-content {
  flex: 1;
  min-width: 0;
}

.stat-label {
  font-size: 0.625rem;
  color: var(--text-light);
  text-transform: uppercase;
  font-weight: 600;
  letter-spacing: 0.3px;
  margin-bottom: 0.125rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.stat-value {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--text-color);
  line-height: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.document-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  justify-content: space-between;
}

.btn-delete-doc {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1.25rem;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  transition: all 0.2s;
  opacity: 0.6;
}

.btn-delete-doc:hover {
  opacity: 1;
  background: rgba(239, 68, 68, 0.1);
}
</style>
