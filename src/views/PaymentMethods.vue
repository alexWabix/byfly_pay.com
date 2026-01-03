<template>
  <div class="payment-methods-page">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Способы оплаты</h3>
      </div>

      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Название</th>
              <th>Страна</th>
              <th>Провайдер</th>
              <th>Комиссия</th>
              <th>Кредит</th>
              <th>Рассрочка</th>
              <th>Статус</th>
              <th>Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="method in paymentMethods" :key="method.id">
              <td><strong>{{ method.name }}</strong></td>
              <td>{{ method.country }}</td>
              <td>{{ method.provider }}</td>
              <td>{{ method.commission_percent }}%</td>
              <td>
                <span v-if="method.has_credit" class="badge badge-warning">
                  {{ method.credit_commission_percent }}%
                </span>
                <span v-else>-</span>
              </td>
              <td>
                <span v-if="method.has_installment" class="badge badge-info">
                  {{ method.installment_months }} мес. ({{ method.installment_commission_percent }}%)
                </span>
                <span v-else>-</span>
              </td>
              <td>
                <span :class="'badge badge-' + (method.is_active ? 'success' : 'secondary')">
                  {{ method.is_active ? 'Активен' : 'Неактивен' }}
                </span>
              </td>
              <td>
                <button @click="editMethod(method)" class="btn btn-sm btn-outline">
                  Настроить
                </button>
              </td>
            </tr>
            <tr v-if="!paymentMethods.length">
              <td colspan="8" class="text-center">Нет способов оплаты</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="showEditModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal">
        <div class="modal-header">
          <h3 class="modal-title">Настройки способа оплаты</h3>
          <button @click="closeModal" class="btn-close">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Название</label>
            <input v-model="form.name" type="text" class="form-control" />
          </div>

          <div class="form-group">
            <label class="form-label">Иконка</label>
            <div class="icon-upload-section">
              <div v-if="form.icon_url || form.icon_emoji" class="current-icon">
                <img v-if="form.icon_url" :src="form.icon_url" alt="Icon" class="icon-preview" />
                <span v-else class="icon-emoji-preview">{{ form.icon_emoji }}</span>
              </div>
              <div class="upload-controls">
                <input 
                  ref="iconInput" 
                  type="file" 
                  accept="image/png,image/jpeg,image/jpg,image/svg+xml" 
                  @change="uploadIcon"
                  style="display: none"
                />
                <button type="button" @click="$refs.iconInput.click()" class="btn btn-outline btn-sm">
                  📁 Выбрать файл
                </button>
                <button type="button" @click="clearIcon" class="btn btn-outline btn-sm" v-if="form.icon_url || form.icon_emoji">
                  🗑️ Удалить
                </button>
              </div>
              <small class="form-hint">PNG, JPG или SVG, макс 2MB</small>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Эмодзи иконка (альтернатива)</label>
            <input v-model="form.icon_emoji" type="text" class="form-control" placeholder="💰" maxlength="10" />
            <small class="form-hint">Используется если нет изображения</small>
          </div>

          <div class="form-group">
            <label class="form-label">Доступен в странах *</label>
            <div class="countries-checkboxes">
              <label v-for="country in countries" :key="country.id" class="checkbox-label">
                <input 
                  type="checkbox" 
                  :value="country.id" 
                  v-model="form.allowed_countries"
                />
                {{ country.flag_emoji }} {{ country.name }}
              </label>
            </div>
            <small class="form-hint">Выберите страны где доступен этот способ оплаты</small>
          </div>

          <div class="form-group">
            <label class="form-label">Валюта платежной системы *</label>
            <select v-model="form.payment_currency" class="form-select">
              <option value="KZT">KZT - Тенге (₸)</option>
              <option value="RUB">RUB - Рубль (₽)</option>
              <option value="UZS">UZS - Сум (сўм)</option>
              <option value="USD">USD - Доллар ($)</option>
              <option value="EUR">EUR - Евро (€)</option>
            </select>
            <small class="form-hint">В какой валюте платежная система принимает оплату</small>
          </div>

          <div class="form-group">
            <label class="form-label">Комиссия (%)</label>
            <input v-model.number="form.commission_percent" type="number" step="0.01" class="form-control" />
          </div>

          <div class="form-group">
            <label class="form-label">
              <input v-model="form.has_credit" type="checkbox" />
              Доступен кредит
            </label>
          </div>

          <div v-if="form.has_credit" class="form-group">
            <label class="form-label">Комиссия за кредит (%)</label>
            <input v-model.number="form.credit_commission_percent" type="number" step="0.01" class="form-control" />
          </div>

          <div class="form-group">
            <label class="form-label">
              <input v-model="form.has_installment" type="checkbox" />
              Доступна рассрочка
            </label>
          </div>

          <div v-if="form.has_installment">
            <div class="form-group">
              <label class="form-label">Количество месяцев</label>
              <input v-model.number="form.installment_months" type="number" class="form-control" />
            </div>

            <div class="form-group">
              <label class="form-label">Комиссия за рассрочку (%)</label>
              <input v-model.number="form.installment_commission_percent" type="number" step="0.01" class="form-control" />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">
              <input v-model="form.add_commission_to_amount" type="checkbox" />
              Добавлять комиссию к сумме платежа
            </label>
          </div>

          <div class="form-group">
            <label class="form-label">
              <input v-model="form.is_active" type="checkbox" />
              Активен
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeModal" class="btn btn-outline">Отмена</button>
          <button @click="saveMethod" class="btn btn-primary">
            Сохранить
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const paymentMethods = ref([])
const countries = ref([])
const showEditModal = ref(false)
const form = ref({})
const editingId = ref(null)

onMounted(() => {
  loadPaymentMethods()
  loadCountries()
})

async function loadCountries() {
  try {
    const response = await api.get('/admin/countries')
    countries.value = response.data || []
  } catch (error) {
    console.error('Failed to load countries:', error)
  }
}

async function loadPaymentMethods() {
  try {
    const response = await api.get('/admin/payment-methods')
    paymentMethods.value = response.data || []
  } catch (error) {
    console.error('Failed to load payment methods:', error)
  }
}

function editMethod(method) {
  // Парсим allowed_countries если это JSON строка
  let allowedCountries = []
  if (method.allowed_countries) {
    try {
      allowedCountries = typeof method.allowed_countries === 'string' 
        ? JSON.parse(method.allowed_countries) 
        : method.allowed_countries
    } catch (e) {
      allowedCountries = method.country_id ? [method.country_id] : []
    }
  } else if (method.country_id) {
    allowedCountries = [method.country_id]
  }
  
  form.value = {
    name: method.name || '',
    icon_url: method.icon_url || '',
    icon_emoji: method.icon_emoji || '',
    allowed_countries: allowedCountries,
    payment_currency: method.payment_currency || 'KZT',
    commission_percent: Number(method.commission_percent) || 0,
    has_credit: Boolean(method.has_credit),
    credit_commission_percent: Number(method.credit_commission_percent) || 0,
    has_installment: Boolean(method.has_installment),
    installment_months: Number(method.installment_months) || null,
    installment_commission_percent: Number(method.installment_commission_percent) || 0,
    add_commission_to_amount: Boolean(method.add_commission_to_amount),
    is_active: Boolean(method.is_active)
  }
  editingId.value = method.id
  showEditModal.value = true
}

async function uploadIcon(event) {
  const file = event.target.files[0]
  if (!file) return

  const formData = new FormData()
  formData.append('icon', file)

  try {
    const response = await api.post('/upload/icon', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    if (response.data && response.data.url) {
      form.value.icon_url = response.data.url
      alert('✅ Иконка загружена!')
    }
  } catch (error) {
    alert('Ошибка загрузки: ' + (error.response?.data?.message || error.message))
  }
}

function clearIcon() {
  form.value.icon_url = ''
  form.value.icon_emoji = ''
}

async function saveMethod() {
  try {
    // Преобразуем boolean в 0/1 для MySQL
    const data = {
      ...form.value,
      has_credit: form.value.has_credit ? 1 : 0,
      has_installment: form.value.has_installment ? 1 : 0,
      add_commission_to_amount: form.value.add_commission_to_amount ? 1 : 0,
      is_active: form.value.is_active ? 1 : 0
    }
    
    // Убираем null/undefined значения
    Object.keys(data).forEach(key => {
      if (data[key] === null || data[key] === undefined || data[key] === '') {
        delete data[key]
      }
    })
    
    await api.put(`/admin/payment-methods/${editingId.value}`, data)
    await loadPaymentMethods()
    closeModal()
  } catch (error) {
    alert(error.response?.data?.message || 'Ошибка сохранения')
  }
}

function closeModal() {
  showEditModal.value = false
  form.value = {}
  editingId.value = null
}
</script>

<style scoped>
.btn-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: var(--text-light);
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.75rem;
}

.icon-upload-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.current-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(102, 126, 234, 0.05);
  border-radius: 0.75rem;
  min-height: 100px;
}

.icon-preview {
  width: 64px;
  height: 64px;
  object-fit: contain;
}

.icon-emoji-preview {
  font-size: 3rem;
}

.upload-controls {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.countries-checkboxes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 0.75rem;
  padding: 1rem;
  background: rgba(102, 126, 234, 0.05);
  border-radius: 0.75rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 0.5rem;
  transition: background 0.2s;
}

.checkbox-label:hover {
  background: rgba(102, 126, 234, 0.1);
}

.checkbox-label input[type="checkbox"] {
  cursor: pointer;
}
</style>

