<template>
  <div class="banks-page">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">🏦 Банковские счета</h3>
        <button @click="showCreateModal = true" class="btn btn-primary">
          ➕ Добавить банк
        </button>
      </div>

      <!-- Компактная таблица -->
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Банк</th>
              <th>Счет</th>
              <th>Страна</th>
              <th>Баланс</th>
              <th>Статус</th>
              <th>Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="bank in banks" :key="bank.id" :class="{ inactive: !bank.is_active }">
              <td>
                <div class="bank-cell">
                  <span class="bank-icon-small" :style="{ background: bank.bank_color }">
                    {{ bank.bank_icon }}
                  </span>
                  <strong>{{ bank.name }}</strong>
                </div>
              </td>
              <td>
                <code class="account-code">{{ bank.account_number || '-' }}</code>
              </td>
              <td>{{ getCountryFlag(bank.country_name) }} {{ bank.country_name }}</td>
              <td>
                <div class="balance-cell">
                  <span class="balance-amount">{{ formatAmount(bank.balance) }}</span>
                  <span class="balance-currency">{{ bank.currency }}</span>
                </div>
              </td>
              <td>
                <span :class="'badge badge-' + (bank.is_active ? 'success' : 'secondary')">
                  {{ bank.is_active ? 'Активен' : 'Неактивен' }}
                </span>
              </td>
              <td>
                <div class="action-buttons">
                  <button @click="viewBankDetails(bank)" class="btn btn-sm btn-outline" title="История">📊</button>
                  <button @click="editBank(bank)" class="btn btn-sm btn-outline" title="Редактировать">✏️</button>
                  <button @click="openAddTransactionModal(bank)" class="btn btn-sm btn-success" title="Добавить операцию">➕</button>
                  <button @click="deleteBank(bank)" class="btn btn-sm btn-danger" title="Удалить">🗑️</button>
                </div>
              </td>
            </tr>
            <tr v-if="!banks.length">
              <td colspan="6" class="text-center">Нет банков</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Модальное окно создания/редактирования банка -->
    <div v-if="showCreateModal || showEditModal" class="modal-overlay" @click.self="closeModals">
      <div class="modal">
        <div class="modal-header">
          <h3>{{ showEditModal ? '✏️ Редактировать банк' : '➕ Добавить банк' }}</h3>
          <button @click="closeModals" class="btn-close">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Название банка *</label>
            <input v-model="form.name" class="form-control" placeholder="Kaspi Bank" required />
          </div>

          <div class="form-group">
            <label class="form-label">Номер счета/карты</label>
            <input v-model="form.account_number" class="form-control" placeholder="4400430199704070" />
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Страна *</label>
              <select v-model="form.country_name" class="form-control" @change="updateCountryCode">
                <option value="Казахстан">🇰🇿 Казахстан</option>
                <option value="Узбекистан">🇺🇿 Узбекистан</option>
                <option value="Азербайджан">🇦🇿 Азербайджан</option>
                <option value="Россия">🇷🇺 Россия</option>
                <option value="Кыргызстан">🇰🇬 Кыргызстан</option>
                <option value="Армения">🇦🇲 Армения</option>
                <option value="Грузия">🇬🇪 Грузия</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Валюта *</label>
              <select v-model="form.currency" class="form-control">
                <option value="KZT">KZT (₸)</option>
                <option value="USD">USD ($)</option>
                <option value="EUR">EUR (€)</option>
                <option value="RUB">RUB (₽)</option>
                <option value="AZN">AZN (₼)</option>
                <option value="UZS">UZS (сўм)</option>
                <option value="AMD">AMD (֏)</option>
                <option value="GEL">GEL (₾)</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Иконка</label>
              <input v-model="form.bank_icon" class="form-control" placeholder="🏦" maxlength="10" />
            </div>

            <div class="form-group">
              <label class="form-label">Цвет</label>
              <input v-model="form.bank_color" type="color" class="form-control" />
            </div>
          </div>

          <div v-if="!showEditModal" class="form-group">
            <label class="form-label">Начальный баланс</label>
            <input v-model="form.initial_balance" type="number" step="0.01" class="form-control" placeholder="0.00" />
          </div>

          <div v-if="showEditModal" class="form-group">
            <label class="form-label">Текущий баланс</label>
            <input v-model="form.balance" type="number" step="0.01" class="form-control" />
            <small class="form-hint warning">⚠️ Изменение создаст транзакцию корректировки</small>
          </div>

          <div class="form-group">
            <label class="form-label">Описание</label>
            <textarea v-model="form.description" class="form-control" rows="2" placeholder="Заметки о счете..."></textarea>
          </div>

          <div class="form-group">
            <label class="checkbox-label">
              <input v-model="form.is_active" type="checkbox" />
              Активен
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeModals" class="btn btn-outline">Отмена</button>
          <button @click="saveBank" class="btn btn-primary">
            {{ showEditModal ? 'Сохранить' : 'Создать' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Модальное окно добавления транзакции -->
    <div v-if="showTransactionModal" class="modal-overlay" @click.self="showTransactionModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3>💰 Операция: {{ selectedBank?.name }}</h3>
          <button @click="showTransactionModal = false" class="btn-close">✕</button>
        </div>
        <div class="modal-body">
          <div class="bank-balance-info">
            <p>Текущий баланс: <strong>{{ formatAmount(selectedBank?.balance) }} {{ selectedBank?.currency }}</strong></p>
          </div>

          <div class="form-group">
            <label class="form-label">Тип операции *</label>
            <select v-model="transactionForm.type" class="form-control">
              <option value="income">➕ Приход</option>
              <option value="expense">➖ Расход</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Сумма *</label>
            <input v-model="transactionForm.amount" type="number" step="0.01" class="form-control" placeholder="0.00" required />
          </div>

          <div class="form-group">
            <label class="form-label">Описание</label>
            <textarea v-model="transactionForm.description" class="form-control" rows="3" placeholder="Описание операции..."></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Номер документа</label>
            <input v-model="transactionForm.reference" class="form-control" placeholder="Чек/квитанция №" />
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showTransactionModal = false" class="btn btn-outline">Отмена</button>
          <button @click="addTransaction" class="btn btn-primary" :disabled="!transactionForm.amount">
            Добавить операцию
          </button>
        </div>
      </div>
    </div>

    <!-- Модальное окно деталей банка -->
    <div v-if="showDetailsModal && selectedBank" class="modal-overlay" @click.self="showDetailsModal = false">
      <div class="modal modal-large">
        <div class="modal-header">
          <h3>{{ selectedBank.bank_icon }} {{ selectedBank.name }}</h3>
          <button @click="showDetailsModal = false" class="btn-close">✕</button>
        </div>
        <div class="modal-body">
          <div class="bank-details-header">
            <div class="detail-card">
              <span class="label">Баланс</span>
              <span class="value">{{ formatAmount(selectedBank.balance) }} {{ selectedBank.currency }}</span>
            </div>
            <div class="detail-card">
              <span class="label">Счет</span>
              <span class="value">{{ selectedBank.account_number || '-' }}</span>
            </div>
            <div class="detail-card">
              <span class="label">Страна</span>
              <span class="value">{{ selectedBank.country_name }}</span>
            </div>
          </div>

          <h4 style="margin: 1.5rem 0 1rem;">История операций</h4>
          <div class="transactions-list">
            <div v-for="tx in selectedBank.transactions" :key="tx.id" class="transaction-item">
              <div :class="['tx-type', tx.type]">
                <span v-if="tx.type === 'income'">➕</span>
                <span v-else-if="tx.type === 'expense'">➖</span>
                <span v-else>🔄</span>
              </div>
              <div class="tx-info">
                <div class="tx-desc">{{ tx.description || 'Без описания' }}</div>
                <div class="tx-meta">
                  {{ formatDate(tx.created_at) }} • {{ tx.admin_name || 'Система' }}
                  <span v-if="tx.reference"> • {{ tx.reference }}</span>
                </div>
              </div>
              <div class="tx-amount">
                <div :class="['amount', tx.type]">
                  {{ tx.type === 'income' ? '+' : '-' }}{{ formatAmount(tx.amount) }}
                </div>
                <div class="balance-after">= {{ formatAmount(tx.balance_after) }}</div>
              </div>
            </div>
            <div v-if="!selectedBank.transactions || selectedBank.transactions.length === 0" class="no-transactions">
              Нет операций
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="showDetailsModal = false" class="btn btn-outline">Закрыть</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const banks = ref([])
const selectedBank = ref(null)
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showTransactionModal = ref(false)
const showDetailsModal = ref(false)
const editingId = ref(null)

const form = ref({
  name: '',
  account_number: '',
  country_code: 'KZ',
  country_name: 'Казахстан',
  currency: 'KZT',
  initial_balance: 0,
  bank_color: '#667eea',
  bank_icon: '🏦',
  description: '',
  is_active: true
})

const transactionForm = ref({
  type: 'income',
  amount: '',
  description: '',
  reference: ''
})

const countryFlags = {
  'Казахстан': '🇰🇿',
  'Узбекистан': '🇺🇿',
  'Азербайджан': '🇦🇿',
  'Россия': '🇷🇺',
  'Кыргызстан': '🇰🇬',
  'Армения': '🇦🇲',
  'Грузия': '🇬🇪'
}

const countryCodes = {
  'Казахстан': 'KZ',
  'Узбекистан': 'UZ',
  'Азербайджан': 'AZ',
  'Россия': 'RU',
  'Кыргызстан': 'KG',
  'Армения': 'AM',
  'Грузия': 'GE'
}

function getCountryFlag(country) {
  return countryFlags[country] || '🏳️'
}

onMounted(() => loadBanks())

async function loadBanks() {
  try {
    const response = await api.get('/banks')
    banks.value = response.data || []
  } catch (error) {
    console.error(error)
    alert('Ошибка загрузки банков')
  }
}

async function viewBankDetails(bank) {
  try {
    const response = await api.get(`/banks/${bank.id}`)
    selectedBank.value = response.data
    showDetailsModal.value = true
  } catch (error) {
    alert('Ошибка загрузки деталей')
  }
}

function editBank(bank) {
  form.value = {
    name: bank.name,
    account_number: bank.account_number || '',
    country_code: bank.country_code,
    country_name: bank.country_name,
    currency: bank.currency,
    balance: bank.balance,
    bank_color: bank.bank_color,
    bank_icon: bank.bank_icon,
    description: bank.description || '',
    is_active: Boolean(bank.is_active)
  }
  editingId.value = bank.id
  showEditModal.value = true
}

async function deleteBank(bank) {
  if (!confirm(`Удалить банк "${bank.name}"?\n\nВсе транзакции также будут удалены!`)) return
  
  try {
    await api.delete(`/banks/${bank.id}`)
    alert('✅ Банк удален')
    await loadBanks()
  } catch (error) {
    alert(error.response?.data?.message || '❌ Ошибка удаления')
  }
}

async function saveBank() {
  if (!form.value.name) {
    alert('Название банка обязательно')
    return
  }
  
  try {
    if (showEditModal.value) {
      await api.put(`/banks/${editingId.value}`, form.value)
      alert('✅ Банк обновлен')
    } else {
      await api.post('/banks', form.value)
      alert('✅ Банк создан')
    }
    await loadBanks()
    closeModals()
  } catch (error) {
    alert(error.response?.data?.message || 'Ошибка сохранения')
  }
}

function openAddTransactionModal(bank) {
  selectedBank.value = bank
  transactionForm.value = {
    type: 'income',
    amount: '',
    description: '',
    reference: ''
  }
  showTransactionModal.value = true
}

async function addTransaction() {
  if (!transactionForm.value.amount || transactionForm.value.amount <= 0) {
    alert('Укажите сумму операции')
    return
  }
  
  try {
    await api.post(`/banks/${selectedBank.value.id}/transaction`, transactionForm.value)
    alert('✅ Операция добавлена')
    showTransactionModal.value = false
    await loadBanks()
  } catch (error) {
    alert(error.response?.data?.message || 'Ошибка добавления операции')
  }
}

function updateCountryCode() {
  form.value.country_code = countryCodes[form.value.country_name] || 'KZ'
}

function closeModals() {
  showCreateModal.value = false
  showEditModal.value = false
  form.value = {
    name: '',
    account_number: '',
    country_code: 'KZ',
    country_name: 'Казахстан',
    currency: 'KZT',
    initial_balance: 0,
    bank_color: '#667eea',
    bank_icon: '🏦',
    description: '',
    is_active: true
  }
  editingId.value = null
}

function formatAmount(value) {
  return Number(value).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleString('ru-RU')
}
</script>

<style scoped>
.bank-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.bank-icon-small {
  width: 36px;
  height: 36px;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: white;
  flex-shrink: 0;
}

.account-code {
  font-family: 'Monaco', 'Consolas', monospace;
  font-size: 0.875rem;
  background: #f5f5f5;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
}

.balance-cell {
  display: flex;
  align-items: baseline;
  gap: 0.25rem;
}

.balance-amount {
  font-weight: 700;
  font-size: 1.125rem;
  color: var(--primary-color);
}

.balance-currency {
  font-size: 0.75rem;
  color: var(--text-light);
}

.action-buttons {
  display: flex;
  gap: 0.375rem;
}

.inactive {
  opacity: 0.5;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.bank-balance-info {
  padding: 1rem;
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
  border-radius: 0.5rem;
  margin-bottom: 1rem;
  text-align: center;
}

.bank-balance-info p {
  margin: 0;
}

.bank-details-header {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 2rem;
}

.detail-card {
  padding: 1rem;
  background: var(--bg-color);
  border-radius: 0.75rem;
  text-align: center;
}

.detail-card .label {
  display: block;
  font-size: 0.75rem;
  color: var(--text-light);
  text-transform: uppercase;
  margin-bottom: 0.5rem;
}

.detail-card .value {
  display: block;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-color);
}

.transactions-list {
  max-height: 400px;
  overflow-y: auto;
}

.transaction-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  border-bottom: 1px solid var(--border-color);
}

.tx-type {
  width: 40px;
  height: 40px;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.tx-type.income {
  background: rgba(16, 185, 129, 0.1);
  color: var(--success-color);
}

.tx-type.expense {
  background: rgba(239, 68, 68, 0.1);
  color: var(--danger-color);
}

.tx-info {
  flex: 1;
}

.tx-desc {
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.tx-meta {
  font-size: 0.75rem;
  color: var(--text-light);
}

.tx-amount {
  text-align: right;
}

.tx-amount .amount {
  font-size: 1.125rem;
  font-weight: 700;
}

.tx-amount .amount.income {
  color: var(--success-color);
}

.tx-amount .amount.expense {
  color: var(--danger-color);
}

.tx-amount .balance-after {
  font-size: 0.75rem;
  color: var(--text-light);
}

.no-transactions {
  text-align: center;
  padding: 2rem;
  color: var(--text-light);
}
</style>

