<template>
  <div class="dashboard">
    <!-- Общий баланс в тенге -->
    <div v-if="bankStats.length > 0" class="total-balance-card">
      <div class="total-balance-header">
        <h2>💰 Общий баланс компании</h2>
        <div class="total-balance-amount">{{ formatAmount(totalBalanceKZT || 0) }} ₸</div>
        <div class="total-balance-note">По курсу на {{ new Date().toLocaleDateString('ru-RU') }}</div>
      </div>
    </div>
    
    <!-- Сообщение если нет банков -->
    <div v-else class="no-banks-card">
      <div class="no-banks-icon">🏦</div>
      <h3>Банковские счета не настроены</h3>
      <p>Добавьте банковские счета для отслеживания балансов</p>
      <router-link to="/banks" class="btn btn-primary">Добавить банк</router-link>
    </div>

    <!-- Балансы по странам и валютам -->
    <div v-if="bankStats.length > 0" class="balances-grid">
      <!-- По странам -->
      <div class="balances-section">
        <h3>🌍 По странам</h3>
        <div class="balance-items">
          <div v-for="country in balancesByCountry" :key="country.name" class="balance-item">
            <div class="balance-label">{{ country.flag }} {{ country.name }}</div>
            <div class="balance-values">
              <div v-for="curr in country.currencies" :key="curr.currency" class="balance-value">
                {{ formatAmount(curr.total) }} {{ curr.currency }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- По валютам -->
      <div class="balances-section">
        <h3>💵 По валютам</h3>
        <div class="balance-items">
          <div v-for="currency in balancesByCurrency" :key="currency.code" class="balance-item">
            <div class="balance-label">{{ currency.symbol }} {{ currency.code }}</div>
            <div class="balance-values">
              <div class="balance-value primary">{{ formatAmount(currency.total) }}</div>
              <div class="balance-value-kzt">≈ {{ formatAmount(currency.totalKZT) }} ₸</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Приход/Расход сегодня -->
    <div v-if="bankStats.length > 0" class="today-stats">
      <div class="today-card">
        <div class="today-icon income">📈</div>
        <div class="today-content">
          <div class="today-label">Приход сегодня</div>
          <div v-for="item in todayIncome" :key="item.currency" class="today-value income">
            +{{ formatAmount(item.amount) }} {{ item.currency }}
          </div>
          <div v-if="!todayIncome.length" class="today-value">0</div>
        </div>
      </div>

      <div class="today-card">
        <div class="today-icon expense">📉</div>
        <div class="today-content">
          <div class="today-label">Расход сегодня</div>
          <div v-for="item in todayExpense" :key="item.currency" class="today-value expense">
            -{{ formatAmount(item.amount) }} {{ item.currency }}
          </div>
          <div v-if="!todayExpense.length" class="today-value">0</div>
        </div>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">💳</div>
        <div class="stat-content">
          <div class="stat-label">Всего транзакций</div>
          <div class="stat-value">{{ stats.total || 0 }}</div>
        </div>
      </div>

      <div class="stat-card success">
        <div class="stat-icon">✅</div>
        <div class="stat-content">
          <div class="stat-label">Успешных</div>
          <div class="stat-value">{{ stats.paid || 0 }}</div>
        </div>
      </div>

      <div class="stat-card warning">
        <div class="stat-icon">⏳</div>
        <div class="stat-content">
          <div class="stat-label">В обработке</div>
          <div class="stat-value">{{ stats.processing || 0 }}</div>
        </div>
      </div>

      <div class="stat-card danger">
        <div class="stat-icon">❌</div>
        <div class="stat-content">
          <div class="stat-label">Отменено</div>
          <div class="stat-value">{{ stats.cancelled || 0 }}</div>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Последние транзакции</h3>
        </div>
        <div class="table-container">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Сумма</th>
                <th>Статус</th>
                <th>Дата</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="transaction in recentTransactions" :key="transaction.id">
                <td>{{ transaction.transaction_id.substring(0, 8) }}...</td>
                <td>{{ formatAmount(transaction.amount) }} {{ transaction.currency }}</td>
                <td><span :class="'badge badge-' + getStatusClass(transaction.status)">
                  {{ getStatusText(transaction.status) }}
                </span></td>
                <td>{{ formatDate(transaction.created_at) }}</td>
              </tr>
              <tr v-if="!recentTransactions.length">
                <td colspan="4" class="text-center">Нет транзакций</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Терминалы Kaspi</h3>
        </div>
        <div class="terminals-list">
          <div v-for="terminal in terminals" :key="terminal.id" class="terminal-item">
            <div class="terminal-info">
              <div class="terminal-name">{{ terminal.name }}</div>
              <div class="terminal-details">
                {{ terminal.ip_address }}:{{ terminal.port }}
              </div>
            </div>
            <div class="terminal-status">
              <span :class="'badge badge-' + (terminal.is_busy ? 'warning' : 'success')">
                {{ terminal.is_busy ? 'Занят' : 'Свободен' }}
              </span>
            </div>
          </div>
          <div v-if="!terminals.length" class="text-center" style="padding: 2rem;">
            Нет терминалов
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const stats = ref({})
const recentTransactions = ref([])
const terminals = ref([])
const bankStats = ref([])
const exchangeRates = ref({})
const todayIncome = ref([])
const todayExpense = ref([])
const totalBalanceKZT = ref(0)
const balancesByCountry = ref([])
const balancesByCurrency = ref([])

// Символы валют
const currencySymbols = {
  'KZT': '₸',
  'USD': '$',
  'EUR': '€',
  'RUB': '₽',
  'AZN': '₼',
  'UZS': 'сўм',
  'AMD': '֏',
  'GEL': '₾'
}

// Функция пересчета статистики
function recalculateBalances() {
  console.log('🔄 Recalculating balances...')
  console.log('📊 Bank stats:', bankStats.value)
  console.log('💱 Exchange rates:', exchangeRates.value)
  
  // Пересчет общего баланса
  let total = 0
  bankStats.value.forEach(stat => {
    const balance = parseFloat(stat.total_balance)
    const rate = exchangeRates.value[stat.currency] || 1
    const converted = balance * rate
    console.log(`💰 ${stat.currency}: ${balance} × ${rate} = ${converted} ₸`)
    total += converted
  })
  totalBalanceKZT.value = total
  console.log(`✅ Total balance: ${total} ₸`)
  
  // Пересчет по странам
  const groupedCountries = {}
  bankStats.value.forEach(stat => {
    if (!groupedCountries[stat.country_name]) {
      groupedCountries[stat.country_name] = {
        name: stat.country_name,
        flag: getCountryFlag(stat.country_name),
        currencies: []
      }
    }
    groupedCountries[stat.country_name].currencies.push({
      currency: stat.currency,
      total: stat.total_balance
    })
  })
  balancesByCountry.value = Object.values(groupedCountries)
  
  // Пересчет по валютам
  const groupedCurrencies = {}
  bankStats.value.forEach(stat => {
    if (!groupedCurrencies[stat.currency]) {
      groupedCurrencies[stat.currency] = {
        code: stat.currency,
        symbol: currencySymbols[stat.currency] || stat.currency,
        total: 0,
        totalKZT: 0
      }
    }
    const balance = parseFloat(stat.total_balance)
    groupedCurrencies[stat.currency].total += balance
    
    const rate = exchangeRates.value[stat.currency] || 1
    groupedCurrencies[stat.currency].totalKZT += balance * rate
  })
  balancesByCurrency.value = Object.values(groupedCurrencies)
  
  console.log('✅ Balances recalculated')
}

onMounted(async () => {
  // ВАЖНО: Загружаем по порядку
  await loadExchangeRates()
  await loadBankStats()
  await loadDashboardData()
  
  // Пересчитываем после загрузки всех данных
  recalculateBalances()
})

async function loadExchangeRates() {
  try {
    const response = await api.get('/countries/exchange-rates')
    const rates = response.data || []
    
    console.log('📊 Exchange rates from API:', rates)
    
    // Создаем объект курсов относительно KZT
    exchangeRates.value = {
      'KZT': 1
    }
    
    // В БД курсы хранятся как from_currency (KZT) -> to_currency с rate
    // Нужно инвертировать: to_currency -> KZT
    rates.forEach(rateObj => {
      // Проверяем что from_currency это KZT
      if (rateObj.from_currency === 'KZT' && rateObj.to_currency && rateObj.rate) {
        const rate = parseFloat(rateObj.rate)
        if (rate > 0) {
          // Инвертируем курс: если 1 KZT = 0.002 USD, то 1 USD = 1/0.002 = 500 KZT
          exchangeRates.value[rateObj.to_currency] = 1 / rate
          console.log(`💱 ${rateObj.to_currency}: 1 / ${rate} = ${1/rate} KZT`)
        }
      }
    })
    
    // Добавляем fallback для валют которых нет в БД
    const fallbackRates = {
      'USD': 512,
      'EUR': 605,
      'RUB': 5.2,
      'AZN': 290,
      'UZS': 0.039,
      'AMD': 1.35,
      'GEL': 197
    }
    
    // Добавляем fallback только если валюты нет
    Object.keys(fallbackRates).forEach(currency => {
      if (!exchangeRates.value[currency]) {
        exchangeRates.value[currency] = fallbackRates[currency]
        console.log(`⚠️ Using fallback for ${currency}: ${fallbackRates[currency]}`)
      }
    })
    
    console.log('✅ Final exchange rates:', exchangeRates.value)
  } catch (error) {
    console.error('Failed to load exchange rates:', error)
    // Используем стандартные курсы
    exchangeRates.value = {
      'KZT': 1,
      'USD': 512,
      'EUR': 605,
      'RUB': 5.2,
      'AZN': 290,
      'UZS': 0.039,
      'AMD': 1.35,
      'GEL': 197
    }
  }
}

async function loadDashboardData() {
  // Проверяем наличие токена перед загрузкой
  if (!localStorage.getItem('token')) {
    return
  }
  
  try {
    const [transactionsData, terminalsData] = await Promise.all([
      api.get('/admin/transactions?limit=10'),
      api.get('/admin/terminals')
    ])

    recentTransactions.value = transactionsData.data || []
    terminals.value = terminalsData.data || []

    // Calculate stats
    const allTransactions = transactionsData.data || []
    stats.value = {
      total: allTransactions.length,
      paid: allTransactions.filter(t => t.status === 'paid').length,
      processing: allTransactions.filter(t => t.status === 'processing').length,
      cancelled: allTransactions.filter(t => t.status === 'cancelled' || t.status === 'failed').length
    }
  } catch (error) {
    console.error('Failed to load dashboard data:', error)
    // Не редиректим здесь - пусть interceptor обработает
  }
}

function formatAmount(amount) {
  return Number(amount).toLocaleString('ru-RU')
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function getStatusClass(status) {
  const classes = {
    'paid': 'success',
    'processing': 'warning',
    'pending': 'info',
    'cancelled': 'secondary',
    'failed': 'danger'
  }
  return classes[status] || 'secondary'
}

function getStatusText(status) {
  const texts = {
    'paid': 'Оплачено',
    'processing': 'В обработке',
    'pending': 'Ожидание',
    'cancelled': 'Отменено',
    'failed': 'Ошибка'
  }
  return texts[status] || status
}

async function loadBankStats() {
  try {
    const response = await api.get('/banks/stats')
    bankStats.value = response.data.stats || []
    
    console.log('🏦 Bank stats loaded:', bankStats.value)
    
    const todayData = response.data.today || []
    todayIncome.value = todayData.map(t => ({ currency: t.currency, amount: t.income_today })).filter(t => t.amount > 0)
    todayExpense.value = todayData.map(t => ({ currency: t.currency, amount: t.expense_today })).filter(t => t.amount > 0)
  } catch (error) {
    console.error('Failed to load bank stats:', error)
  }
}

function getCountryFlag(country) {
  const flags = {
    'Казахстан': '🇰🇿',
    'Узбекистан': '🇺🇿',
    'Азербайджан': '🇦🇿',
    'Россия': '🇷🇺',
    'Кыргызстан': '🇰🇬',
    'Армения': '🇦🇲',
    'Грузия': '🇬🇪'
  }
  return flags[country] || '🏳️'
}
</script>

<style scoped>
.dashboard {
  max-width: 1400px;
  animation: fadeIn 0.6s ease-out;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2.5rem;
}

.stat-card {
  background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
  border-radius: 1.25rem;
  padding: 2rem;
  display: flex;
  align-items: center;
  gap: 1.5rem;
  box-shadow: 0 10px 30px rgba(102, 126, 234, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.8);
  position: relative;
  overflow: hidden;
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  animation: slideInRight 0.5s ease-out;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 200%;
  height: 200%;
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
  opacity: 0.05;
  border-radius: 50%;
  transition: all 0.6s ease;
}

.stat-card:hover {
  transform: translateY(-8px) scale(1.02);
  box-shadow: 0 20px 40px rgba(102, 126, 234, 0.25);
}

.stat-card:hover::before {
  opacity: 0.1;
  transform: scale(1.2);
}

.stat-card.success::before {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card.warning::before {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-card.danger::before {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.stat-icon {
  font-size: 3.5rem;
  filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
  animation: pulse 2s infinite;
}

.stat-label {
  font-size: 0.875rem;
  color: var(--text-light);
  margin-bottom: 0.5rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-value {
  font-size: 2.5rem;
  font-weight: 800;
  background: var(--bg-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.grid-2 {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
  gap: 2rem;
}

.terminals-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.terminal-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 249, 255, 0.9) 100%);
  border-radius: 1rem;
  border: 1px solid rgba(102, 126, 234, 0.1);
  transition: all 0.3s ease;
  animation: fadeIn 0.5s ease-out;
}

.terminal-item:hover {
  transform: translateX(4px);
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15);
  border-color: var(--primary-color);
}

.terminal-name {
  font-weight: 700;
  margin-bottom: 0.5rem;
  color: var(--text-color);
}

.terminal-details {
  font-size: 0.75rem;
  color: var(--text-light);
  font-weight: 500;
}

.balances-overview {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.balance-card {
  padding: 1.5rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 1rem;
  color: white;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
  transition: all 0.3s;
}

.balance-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.balance-country {
  font-size: 0.875rem;
  opacity: 0.9;
  margin-bottom: 0.5rem;
}

.balance-amount {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.balance-meta {
  font-size: 0.75rem;
  opacity: 0.8;
}

.today-stats {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.today-card {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  padding: 1.5rem;
  background: white;
  border-radius: 1rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.today-icon {
  width: 60px;
  height: 60px;
  border-radius: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
}

.today-icon.income {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
}

.today-icon.expense {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
}

.today-content {
  flex: 1;
}

.today-label {
  font-size: 0.875rem;
  color: var(--text-light);
  margin-bottom: 0.5rem;
  text-transform: uppercase;
  font-weight: 600;
}

.today-value {
  font-size: 1.5rem;
  font-weight: 700;
}

.today-value.income {
  color: var(--success-color);
}

.today-value.expense {
  color: var(--danger-color);
}

.total-balance-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 1.5rem;
  padding: 2.5rem;
  color: white;
  box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
  margin-bottom: 2rem;
  text-align: center;
}

.total-balance-header h2 {
  margin: 0 0 1rem 0;
  font-size: 1.5rem;
  opacity: 0.95;
}

.total-balance-amount {
  font-size: 4rem;
  font-weight: 800;
  margin: 1rem 0;
  text-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.total-balance-note {
  opacity: 0.8;
  font-size: 0.875rem;
}

.balances-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.balances-section {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.balances-section h3 {
  margin: 0 0 1rem 0;
  font-size: 1.125rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid var(--border-color);
}

.balance-items {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.balance-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--bg-color);
  border-radius: 0.5rem;
  transition: all 0.2s;
}

.balance-item:hover {
  background: rgba(102, 126, 234, 0.05);
}

.balance-label {
  font-weight: 600;
  color: var(--text-color);
}

.balance-values {
  text-align: right;
}

.balance-value {
  font-weight: 700;
  color: var(--primary-color);
}

.balance-value.primary {
  font-size: 1.25rem;
}

.balance-value-kzt {
  font-size: 0.75rem;
  color: var(--text-light);
  margin-top: 0.125rem;
}

@media (max-width: 1024px) {
  .balances-grid {
    grid-template-columns: 1fr;
  }
}

.no-banks-card {
  background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
  border-radius: 1.5rem;
  padding: 3rem;
  text-align: center;
  margin-bottom: 2rem;
  border: 2px dashed var(--border-color);
}

.no-banks-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.no-banks-card h3 {
  margin: 0 0 0.5rem 0;
  color: var(--text-color);
}

.no-banks-card p {
  margin: 0 0 1.5rem 0;
  color: var(--text-light);
}
</style>

