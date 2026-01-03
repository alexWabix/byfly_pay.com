<template>
  <div class="payment-page">
    <!-- Хедер -->
    <div class="payment-header">
      <h1 class="logo">ByFly Pay</h1>
      <p class="tagline">Безопасная оплата онлайн</p>
    </div>

    <!-- Загрузка -->
    <div v-if="loading" class="loading-container">
      <div class="spinner-large"></div>
      <p>Загрузка платежа...</p>
    </div>

    <!-- Ошибка -->
    <div v-else-if="error" class="error-container">
      <div class="error-icon">❌</div>
      <h2>Ошибка</h2>
      <p>{{ error }}</p>
    </div>

    <!-- Платеж не найден или истек -->
    <div v-else-if="!transaction || transaction.status === 'cancelled'" class="error-container">
      <div class="error-icon">⚠️</div>
      <h2>Платеж недоступен</h2>
      <p>Платеж не найден или был отменен</p>
    </div>

    <!-- Платеж уже оплачен -->
    <div v-else-if="transaction.status === 'paid'" class="success-container">
      <div class="success-icon">✓</div>
      <h2>Платеж выполнен!</h2>
      <p>Спасибо за оплату</p>
      <div class="payment-details">
        <div class="detail-row">
          <span>Сумма:</span>
          <strong>{{ formatAmount(transaction.amount) }} {{ transaction.currency }}</strong>
        </div>
        <div class="detail-row">
          <span>Оплачено:</span>
          <strong>{{ formatDate(transaction.paid_at) }}</strong>
        </div>
      </div>
    </div>

    <!-- Частичная оплата - требуется доплата (только если не выбираем способ и не в процессе оплаты!) -->
    <div v-else-if="(transaction.status === 'partially_paid' || transaction.needs_additional_payment) && step !== 'select' && step !== 'payment'" class="payment-container">
      <div class="payment-card">
        <div class="warning-header">
          <div class="warning-icon">⚠️</div>
          <h2>Требуется доплата</h2>
        </div>

        <div class="payment-mismatch-warning" v-if="transaction.payment_mismatch">
          <div class="mismatch-header">
            <div class="mismatch-icon">❗</div>
            <h3>ВНИМАНИЕ! Несоответствие способа оплаты</h3>
          </div>
          <p class="mismatch-text">
            <strong>Вы выбрали один способ оплаты, но оплатили другим!</strong><br><br>
            Разные способы оплаты имеют разные комиссии банка:<br>
            • Kaspi Gold/Red: <strong>1%</strong> комиссия<br>
            • Kaspi Кредит/Рассрочка: <strong>14%</strong> комиссия<br><br>
            Так как вы оплатили способом с более высокой комиссией, образовалась недоплата.
          </p>
        </div>

        <div class="payment-details">
          <div class="detail-row">
            <span>Было к оплате:</span>
            <strong>{{ formatAmount(transaction.amount) }} {{ transaction.currency }}</strong>
          </div>
          <div class="detail-row">
            <span>Фактически получено:</span>
            <strong>{{ formatAmount(transaction.actual_amount_received || transaction.paid_amount) }} {{ transaction.currency }}</strong>
          </div>
          <div class="detail-row highlight">
            <span>Осталось оплатить:</span>
            <strong class="shortage-amount">{{ formatAmount(transaction.remaining_amount) }} {{ transaction.currency }}</strong>
          </div>
        </div>

        <div class="info-message">
          <p>💡 Выберите способ оплаты для доплаты остатка</p>
        </div>

        <button @click="goToSelectMethodForRemaining" class="btn btn-primary btn-lg btn-block">
          Выбрать способ для доплаты →
        </button>

        <button @click="cancelPayment" class="btn btn-outline">
          Отменить платеж
        </button>
      </div>
    </div>

    <!-- Шаг 1: Выбор страны -->
    <div v-else-if="step === 'country'" class="payment-container">
      <div class="payment-card">
        <h2>Выберите страну</h2>
        <div class="amount-display">
          <div class="amount-label">Сумма к оплате</div>
          <div class="amount-value">{{ formatAmount(convertedAmount || transaction.remaining_amount) }} {{ selectedCountry?.currency_symbol || '₸' }}</div>
        </div>

        <div class="countries-grid">
          <div
            v-for="country in countries"
            :key="country.id"
            @click="selectCountry(country)"
            class="country-card"
            :class="{ 'selected': selectedCountry?.id === country.id }"
          >
            <div class="country-flag">{{ country.flag_emoji }}</div>
            <div class="country-name">{{ country.name }}</div>
          </div>
        </div>

        <button 
          @click="goToContacts" 
          class="btn btn-primary btn-lg btn-block"
          :disabled="!selectedCountry"
        >
          Продолжить →
        </button>
      </div>
    </div>

    <!-- Шаг 2: Контактные данные -->
    <div v-else-if="step === 'contacts'" class="payment-container">
      <div class="payment-card">
        <button @click="step = 'country'" class="btn-back">← Назад</button>
        
        <h2>Контактные данные</h2>
        <p class="subtitle">Мы отправим чек на указанные контакты</p>

        <div class="amount-display-small">
          <span>{{ formatAmount(transaction.remaining_amount) }} {{ selectedCountry?.currency_symbol }}</span>
        </div>

        <div class="form-group">
          <label class="form-label">Email *</label>
          <input 
            v-model="customerEmail" 
            type="email" 
            class="form-control" 
            :class="{ 'input-error': emailError }"
            placeholder="example@mail.com"
            @input="validateEmail"
            @blur="validateEmail"
          />
          <small v-if="emailError" class="form-error">{{ emailError }}</small>
        </div>

        <div class="form-group">
          <label class="form-label">Телефон *</label>
          <input 
            v-model="customerPhone" 
            type="tel" 
            class="form-control" 
            :placeholder="selectedCountry?.phone_mask || '+7 (###) ###-##-##'"
            @input="saveToLocalStorage"
          />
          <small class="form-hint">{{ selectedCountry?.phone_mask || 'Введите номер телефона' }}</small>
        </div>

        <button 
          @click="proceedToPayment" 
          class="btn btn-primary btn-lg btn-block"
          :disabled="!isContactsValid"
        >
          Выбрать способ оплаты →
        </button>
      </div>
    </div>

    <!-- Шаг 3: Выбор способа оплаты -->
    <div v-else-if="step === 'select'" class="payment-container">
      <div class="payment-card">
        <button @click="step = 'contacts'" class="btn-back">← Назад</button>
        
        <!-- Информация о платеже -->
        <div class="payment-info-section">
          <h2>Оплата</h2>
          <div class="amount-display">
            <div class="amount-label">Сумма к оплате</div>
            <div class="amount-value">{{ formatAmount(transaction.remaining_amount) }} {{ selectedCountry?.currency_symbol }}</div>
          </div>
          
          <div v-if="transaction.description" class="description">
            <strong>Описание:</strong>
            <p>{{ transaction.description }}</p>
          </div>

          <div class="contacts-info">
            <div v-if="customerEmail">📧 {{ customerEmail }}</div>
            <div v-if="customerPhone">📱 {{ customerPhone }}</div>
          </div>
        </div>

        <!-- Выбор способа оплаты -->
        <div class="payment-methods-section">
          <h3>Выберите способ оплаты</h3>
          
          <div class="payment-methods-list">
            <div 
              v-for="method in paymentMethods" 
              :key="method.id"
              @click="selectMethod(method)"
              class="payment-method-item"
              :class="{ 'selected': selectedMethod?.id === method.id }"
            >
              <div class="method-header">
                <div class="method-icon">
                  <img v-if="method.icon_url" :src="method.icon_url" alt="" class="method-icon-image" />
                  <span v-else>{{ method.icon_emoji || getMethodIcon(method.code) }}</span>
                </div>
                <div class="method-info">
                  <div class="method-name">{{ method.name }}</div>
                  <div class="method-provider">{{ method.provider }}</div>
                </div>
                <div class="method-badge" v-if="method.code.includes('credit')">
                  🏦 Кредит
                </div>
                <div class="method-badge" v-else-if="method.code.includes('installment')">
                  📅 Рассрочка {{ method.installment_months }} мес
                </div>
              </div>
              
              <div class="method-details">
                <div class="detail-item">
                  <span>Комиссия:</span>
                  <strong>{{ getCommission(method) }}%</strong>
                </div>
                <div class="detail-item total">
                  <span>К оплате:</span>
                  <strong class="total-amount">{{ calculateTotalRaw(method) }} ₸</strong>
                </div>
              </div>

              <!-- Условия для кредита/рассрочки -->
              <div v-if="method.has_credit || method.has_installment" class="method-conditions">
                <div class="conditions-title">📋 Условия:</div>
                <ul>
                  <li v-if="method.has_credit">
                    Кредит под {{ method.credit_commission_percent }}% годовых
                  </li>
                  <li v-if="method.has_installment">
                    Рассрочка на {{ method.installment_months }} месяцев без переплаты
                  </li>
                  <li v-if="method.has_installment">
                    Комиссия банка {{ method.installment_commission_percent }}% (Клиент оплачивает банку)
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <button 
            @click="initiatePayment" 
            class="btn btn-primary btn-lg btn-block"
            :disabled="!selectedMethod || processing"
          >
            <span v-if="processing">Обработка...</span>
            <span v-else>Оплатить {{ selectedMethod ? calculateTotal(selectedMethod) : '' }} ₸</span>
          </button>
        </div>
      </div>
    </div>

    <!-- QR код и ожидание оплаты -->
    <div v-else-if="step === 'payment'" class="payment-container">
      <div class="payment-card">
        <div class="qr-section">
          <h2>Сканируйте QR код для оплаты</h2>
          
          <div class="payment-amount-info">
            <div class="info-item">
              <span>Способ оплаты:</span>
              <strong>{{ selectedMethod?.name }}</strong>
            </div>
            <div class="info-item">
              <span>Сумма к оплате:</span>
              <strong class="highlight">{{ formatAmount(paymentData.amount) }} ₸</strong>
            </div>
            <div v-if="paymentData.commission_amount > 0" class="info-item small">
              <span>Комиссия:</span>
              <span>{{ formatAmount(paymentData.commission_amount) }} ₸ ({{ paymentData.commission_percent }}%)</span>
            </div>
            <div class="info-item small">
              <span>Чистыми к получению:</span>
              <span>{{ formatAmount(paymentData.original_amount) }} ₸</span>
            </div>
          </div>

          <!-- QR код -->
          <div class="qr-code-container">
            <div class="qr-wrapper">
              <img v-if="qrCodeUrl" :src="qrCodeUrl" alt="QR Code" class="qr-image" />
              <div v-else class="qr-loading">
                <div class="spinner"></div>
              </div>
            </div>
            <p class="qr-hint">Откройте приложение Kaspi и отсканируйте QR код</p>
          </div>

          <!-- Ссылка для мобильных -->
          <div class="mobile-payment">
            <a :href="paymentData.payment_url" class="btn btn-kaspi btn-lg btn-block">
              Открыть в приложении Kaspi
            </a>
          </div>

          <!-- Статус -->
          <div class="payment-status">
            <div class="status-indicator">
              <div class="pulse-ring"></div>
              <div class="status-text">Ожидаем оплату...</div>
            </div>
          </div>

          <!-- Таймер -->
          <div class="timer-section">
            <div class="timer-icon">⏱️</div>
            <div class="timer-value">{{ formatTime(remainingTime) }}</div>
          </div>

          <button @click="cancelPayment" class="btn btn-outline">
            Отменить платеж
          </button>
        </div>
      </div>
    </div>

    <!-- Футер -->
    <div class="payment-footer">
      <p class="powered-by">Powered by ByFly Travel Payment Center</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import QRCode from 'qrcode'

const route = useRoute()

const loading = ref(true)
const error = ref(null)
const transaction = ref(null)
const countries = ref([])
const paymentMethods = ref([])
const exchangeRates = ref([])
const step = ref('country') // country, contacts, select, payment, success
const selectedCountry = ref(null)
const selectedMethod = ref(null)
const customerEmail = ref('')
const customerPhone = ref('')
const emailError = ref('')
const processing = ref(false)
const paymentData = ref(null)
const qrCodeUrl = ref('')
const remainingTime = ref(150)
const convertedAmount = ref(0)

let statusCheckInterval = null
let timerInterval = null

const isContactsValid = computed(() => {
  return customerEmail.value && !emailError.value && customerPhone.value
})

onMounted(() => {
  loadPayment()
  loadCountries()
  loadExchangeRates()
  loadFromLocalStorage()
  trackVisit()
  
  // Обновляем курсы каждую минуту
  setInterval(loadExchangeRates, 60000)
})

onUnmounted(() => {
  clearIntervals()
})

async function loadPayment() {
  const transactionId = route.params.id
  
  if (!transactionId) {
    error.value = 'ID платежа не указан'
    loading.value = false
    return
  }

  try {
    // Загружаем данные платежа через публичный API
    const response = await fetch(`/api/payment/${transactionId}`)
    const result = await response.json()
    
    if (!result.success) {
      throw new Error(result.message || 'Платеж не найден')
    }
    
    transaction.value = result.data
    
    // Если partially_paid - устанавливаем страну и загружаем методы
    if (result.data.status === 'partially_paid' || result.data.needs_additional_payment) {
      // Устанавливаем Казахстан по умолчанию если нет выбранной страны
      if (!selectedCountry.value && countries.value.length > 0) {
        selectedCountry.value = countries.value.find(c => c.code === 'KZ') || countries.value[0]
      }
      convertedAmount.value = result.data.remaining_amount
    }
    
    // Загружаем доступные способы оплаты
    await loadPaymentMethods()
    
    loading.value = false
  } catch (err) {
    error.value = err.message || 'Ошибка загрузки платежа'
    loading.value = false
  }
}

async function loadPaymentMethods() {
  try {
    console.log('🔍 Loading payment methods for country:', selectedCountry.value?.code)
    
    const response = await fetch('/api/payment-methods/active')
    const result = await response.json()
    
    if (result.success) {
      const allMethods = result.data || []
      
      // Фильтруем по выбранной стране
      if (selectedCountry.value) {
        paymentMethods.value = allMethods.filter(m => {
          // Проверяем массив allowed_countries
          if (m.allowed_countries) {
            try {
              const allowedCountries = typeof m.allowed_countries === 'string' 
                ? JSON.parse(m.allowed_countries) 
                : m.allowed_countries
              return allowedCountries.includes(selectedCountry.value.id)
            } catch (e) {
              return false
            }
          }
          // Fallback на старое поле country_id
          return m.country_id === selectedCountry.value.id
        })
      } else {
        paymentMethods.value = allMethods
      }
      
      console.log('✅ Payment methods for', selectedCountry.value?.name, ':', paymentMethods.value.length)
    }
  } catch (err) {
    console.error('❌ Failed to load payment methods:', err)
  }
}

async function loadCountries() {
  try {
    const response = await fetch('/api/countries/active')
    const result = await response.json()
    
    if (result.success) {
      countries.value = result.data || []
      // Автоматически выбираем Казахстан по умолчанию
      if (countries.value.length > 0 && !selectedCountry.value) {
        const defaultCountry = countries.value.find(c => c.code === 'KZ') || countries.value[0]
        selectedCountry.value = defaultCountry
        updateConvertedAmount()
      }
    }
  } catch (err) {
    console.error('Failed to load countries:', err)
  }
}

async function loadExchangeRates() {
  try {
    const response = await fetch('/api/countries/exchange-rates')
    const result = await response.json()
    
    if (result.success) {
      exchangeRates.value = result.data || []
      updateConvertedAmount()
    }
  } catch (err) {
    console.error('Failed to load exchange rates:', err)
  }
}

function updateConvertedAmount() {
  if (!transaction.value || !selectedCountry.value) {
    convertedAmount.value = 0
    return
  }

  const baseAmount = parseFloat(transaction.value.remaining_amount)
  const baseCurrency = transaction.value.currency || 'KZT'
  const targetCurrency = selectedCountry.value.currency_code

  if (baseCurrency === targetCurrency) {
    convertedAmount.value = baseAmount
    return
  }

  // Ищем курс
  const rate = exchangeRates.value.find(r => 
    r.from_currency === baseCurrency && r.to_currency === targetCurrency
  )

  if (rate) {
    convertedAmount.value = Math.ceil(baseAmount * parseFloat(rate.rate))
  } else {
    convertedAmount.value = baseAmount
  }
}

function selectCountry(country) {
  selectedCountry.value = country
  // Пересчитываем сумму в валюте выбранной страны
  updateConvertedAmount()
  // Обновляем маску телефона
  if (country.phone_code && !customerPhone.value) {
    customerPhone.value = country.phone_code
  }
  // Перезагружаем способы оплаты для выбранной страны
  loadPaymentMethods()
}

function goToContacts() {
  if (!selectedCountry.value) {
    alert('Выберите страну')
    return
  }
  step.value = 'contacts'
}

function validateEmail() {
  emailError.value = ''
  
  if (!customerEmail.value) {
    return
  }
  
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!emailRegex.test(customerEmail.value)) {
    emailError.value = 'Некорректный email адрес'
  } else {
    saveToLocalStorage()
  }
}

function loadFromLocalStorage() {
  try {
    const saved = localStorage.getItem('byfly_customer_data')
    if (saved) {
      const data = JSON.parse(saved)
      customerEmail.value = data.email || ''
      customerPhone.value = data.phone || ''
    }
  } catch (err) {
    console.error('Failed to load from localStorage:', err)
  }
}

function saveToLocalStorage() {
  try {
    const data = {
      email: customerEmail.value,
      phone: customerPhone.value
    }
    localStorage.setItem('byfly_customer_data', JSON.stringify(data))
  } catch (err) {
    console.error('Failed to save to localStorage:', err)
  }
}

function proceedToPayment() {
  if (!customerEmail.value && !customerPhone.value) {
    alert('Укажите email или телефон для отправки чека')
    return
  }
  step.value = 'select'
}

async function trackVisit() {
  // Отправляем информацию о том, что клиент открыл страницу оплаты
  const transactionId = route.params.id
  if (!transactionId) return
  
  try {
    await fetch(`/api/payment/${transactionId}/track-visit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }
    })
  } catch (err) {
    console.error('Failed to track visit:', err)
  }
}

function selectMethod(method) {
  selectedMethod.value = method
}

function getMethodIcon(code) {
  const icons = {
    'kaspi_gold': '💰',
    'kaspi_red': '💳',
    'kaspi_credit': '🏦',
    'kaspi_installment_12': '📅',
    'kaspi_installment_24': '📆'
  }
  return icons[code] || '💳'
}

function getCommission(method) {
  console.log('📊 getCommission:', {
    code: method.code,
    has_credit: method.has_credit,
    has_installment: method.has_installment,
    credit_commission_percent: method.credit_commission_percent,
    installment_commission_percent: method.installment_commission_percent,
    commission_percent: method.commission_percent
  })
  
  if (method.has_credit) return parseFloat(method.credit_commission_percent)
  if (method.has_installment) return parseFloat(method.installment_commission_percent)
  return parseFloat(method.commission_percent)
}

function calculateTotalRaw(method) {
  // Базовая сумма в валюте выбранной страны
  const amountInClientCurrency = parseFloat(convertedAmount.value || transaction.value.remaining_amount)
  const clientCurrency = selectedCountry.value?.currency_code || 'KZT'
  const paymentCurrency = method.payment_currency || 'KZT'
  
  // Если валюты не совпадают - конвертируем в валюту платежной системы
  let baseAmount = amountInClientCurrency
  
  if (clientCurrency !== paymentCurrency) {
    // Конвертируем обратно через курсы
    const rate = exchangeRates.value.find(r => 
      r.from_currency === 'KZT' && r.to_currency === clientCurrency
    )
    
    if (rate) {
      // Сначала конвертируем в KZT
      const amountInKZT = amountInClientCurrency / parseFloat(rate.rate)
      
      // Если payment_currency тоже не KZT, конвертируем дальше
      if (paymentCurrency !== 'KZT') {
        const rateToPayment = exchangeRates.value.find(r => 
          r.from_currency === 'KZT' && r.to_currency === paymentCurrency
        )
        baseAmount = rateToPayment ? amountInKZT * parseFloat(rateToPayment.rate) : amountInKZT
      } else {
        baseAmount = amountInKZT
      }
    }
  }
  
  const commissionPercent = getCommission(method)
  
  // Добавляем комиссию
  if (method.has_credit || method.has_installment || method.add_commission_to_amount) {
    const commission = (baseAmount * commissionPercent) / 100
    const total = baseAmount + Math.ceil(commission)
    return formatAmount(total)
  }
  
  return formatAmount(baseAmount)
}

function calculateTotal(method) {
  return calculateTotalRaw(method)
}

async function initiatePayment() {
  if (!selectedMethod.value) return
  
  processing.value = true
  
  try {
    const response = await fetch(`/api/payment/${transaction.value.transaction_id}/kaspi`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        payment_method: selectedMethod.value.code
      })
    })
    
    const result = await response.json()
    
    if (!result.success) {
      throw new Error(result.message || 'Ошибка инициализации платежа')
    }
    
    paymentData.value = result.data
    
    // Генерируем QR код
    qrCodeUrl.value = await QRCode.toDataURL(result.data.qr_code || result.data.payment_url, {
      width: 300,
      margin: 2
    })
    
    step.value = 'payment'
    startTimer()
    startStatusMonitoring()
    
  } catch (err) {
    alert(err.message || 'Ошибка при создании платежа')
  } finally {
    processing.value = false
  }
}

function startTimer() {
  remainingTime.value = 150
  
  timerInterval = setInterval(() => {
    remainingTime.value--
    
    if (remainingTime.value <= 0) {
      clearIntervals()
      alert('Время на оплату истекло')
      step.value = 'select'
    }
  }, 1000)
}

function startStatusMonitoring() {
  statusCheckInterval = setInterval(async () => {
    try {
      const response = await fetch(`/api/payment/${transaction.value.transaction_id}/status`)
      const result = await response.json()
      
      if (result.success && result.data) {
        const status = result.data.status
        
        if (status === 'paid') {
          clearIntervals()
          transaction.value = { ...transaction.value, ...result.data }
          
          setTimeout(() => {
            location.reload()
          }, 2000)
          
        } else if (status === 'partially_paid') {
          // Частичная оплата - требуется доплата
          clearIntervals()
          transaction.value = { ...transaction.value, ...result.data }
          location.reload() // Перезагружаем чтобы показать экран доплаты
          
        } else if (status === 'failed' || status === 'cancelled' || status === 'pending') {
          // Оплата отменена клиентом или произошла ошибка
          // Статус 'pending' означает что транзакция сброшена после отмены
          clearIntervals()
          
          // Очищаем данные оплаты
          paymentData.value = null
          qrCodeUrl.value = ''
          
          // Показываем сообщение
          alert('⚠️ Оплата отменена\n\nВы отменили операцию на терминале.\nВыберите способ оплаты заново.')
          
          // Возвращаем на выбор способа оплаты
          step.value = 'select'
          selectedMethod.value = null
        }
      }
    } catch (err) {
      console.error('Status check error:', err)
    }
  }, 1000) // Проверяем КАЖДУЮ СЕКУНДУ
}

async function goToSelectMethodForRemaining() {
  console.log('🔄 Going to select method for remaining amount...')
  
  // Обновляем данные транзакции
  try {
    const response = await fetch(`/api/payment/${transaction.value.transaction_id}`)
    const result = await response.json()
    
    if (result.success) {
      transaction.value = result.data
      convertedAmount.value = result.data.remaining_amount
      console.log('✅ Transaction reloaded, remaining:', result.data.remaining_amount)
    }
  } catch (err) {
    console.error('Failed to reload transaction:', err)
  }
  
  // Загружаем страны если не загружены
  if (countries.value.length === 0) {
    await loadCountries()
  }
  
  // Загружаем способы оплаты
  await loadPaymentMethods()
  
  // Переходим к выбору способа
  step.value = 'select'
  selectedMethod.value = null
  
  console.log('✅ Step changed to select, methods loaded:', paymentMethods.value.length)
}

function cancelPayment() {
  if (!confirm('Вы уверены, что хотите отменить платеж?')) return
  
  clearIntervals()
  step.value = 'select'
  selectedMethod.value = null
  paymentData.value = null
  qrCodeUrl.value = ''
}

function clearIntervals() {
  if (statusCheckInterval) clearInterval(statusCheckInterval)
  if (timerInterval) clearInterval(timerInterval)
}

function formatAmount(value) {
  return Number(value).toLocaleString('ru-RU')
}

function formatTime(seconds) {
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
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
</script>

<style scoped>
.payment-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 2rem 1rem;
}

.payment-header {
  text-align: center;
  color: white;
  margin-bottom: 2rem;
}

.logo {
  font-size: 2.5rem;
  font-weight: 800;
  margin: 0;
  text-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.tagline {
  font-size: 1rem;
  opacity: 0.9;
  margin: 0.5rem 0 0 0;
}

.payment-container,
.loading-container,
.error-container,
.success-container {
  max-width: 600px;
  margin: 0 auto;
  animation: fadeIn 0.5s ease-out;
}

.payment-card {
  background: white;
  border-radius: 1.5rem;
  padding: 2rem;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.loading-container,
.error-container,
.success-container {
  background: white;
  border-radius: 1.5rem;
  padding: 3rem 2rem;
  text-align: center;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.spinner-large {
  width: 60px;
  height: 60px;
  border: 5px solid rgba(102, 126, 234, 0.2);
  border-top-color: var(--primary-color);
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem auto;
}

.error-icon,
.success-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
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
}

.payment-info-section h2 {
  text-align: center;
  margin-bottom: 1.5rem;
  background: var(--bg-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.amount-display {
  text-align: center;
  padding: 2rem;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
  border-radius: 1rem;
  margin-bottom: 1.5rem;
}

.amount-label {
  font-size: 0.875rem;
  color: var(--text-light);
  text-transform: uppercase;
  letter-spacing: 1px;
  font-weight: 600;
}

.amount-value {
  font-size: 2.5rem;
  font-weight: 800;
  background: var(--bg-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-top: 0.5rem;
}

.description {
  padding: 1rem;
  background: rgba(102, 126, 234, 0.05);
  border-radius: 0.75rem;
  margin-bottom: 2rem;
}

.payment-methods-section h3 {
  margin-bottom: 1rem;
  color: var(--text-color);
}

.payment-methods-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.payment-method-item {
  padding: 1rem;
  border: 2px solid rgba(102, 126, 234, 0.15);
  border-radius: 0.75rem;
  cursor: pointer;
  transition: all 0.3s ease;
  background: rgba(102, 126, 234, 0.02);
}

.payment-method-item:hover {
  border-color: var(--primary-color);
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15);
  transform: translateY(-2px);
}

.payment-method-item.selected {
  border-color: var(--primary-color);
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.2);
}

.method-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.method-icon {
  font-size: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.method-icon-image {
  width: 48px;
  height: 48px;
  object-fit: contain;
}

.method-info {
  flex: 1;
}

.method-name {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--text-color);
}

.method-provider {
  font-size: 0.875rem;
  color: var(--text-light);
}

.method-badge {
  padding: 0.375rem 0.75rem;
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
  border-radius: 2rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.method-details {
  display: flex;
  justify-content: space-between;
  padding-top: 1rem;
  border-top: 1px solid rgba(102, 126, 234, 0.1);
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-item.total {
  align-items: flex-end;
}

.total-amount {
  font-size: 1.25rem;
  color: var(--primary-color);
}

.method-conditions {
  margin-top: 1rem;
  padding: 1rem;
  background: rgba(245, 158, 11, 0.1);
  border-radius: 0.75rem;
  font-size: 0.875rem;
}

.conditions-title {
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.method-conditions ul {
  margin: 0;
  padding-left: 1.5rem;
}

.method-conditions li {
  margin: 0.25rem 0;
}

.btn-lg {
  padding: 1.25rem 2rem;
  font-size: 1.125rem;
}

.btn-block {
  width: 100%;
}

.qr-section {
  text-align: center;
}

.qr-section h2 {
  margin-bottom: 1.5rem;
  background: var(--bg-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.payment-amount-info {
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
  border-radius: 1rem;
  padding: 1.5rem;
  margin-bottom: 2rem;
}

.info-item {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem 0;
  border-bottom: 1px solid rgba(102, 126, 234, 0.1);
}

.info-item:last-child {
  border-bottom: none;
}

.info-item.small {
  font-size: 0.875rem;
  color: var(--text-light);
}

.info-item .highlight {
  font-size: 1.25rem;
  color: var(--primary-color);
}

.qr-code-container {
  margin: 2rem 0;
}

.qr-wrapper {
  background: white;
  padding: 1.5rem;
  border-radius: 1rem;
  display: inline-block;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
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

.mobile-payment {
  margin: 2rem 0;
}

.btn-kaspi {
  background: linear-gradient(135deg, #f14635 0%, #c91e0e 100%);
  color: white;
}

.payment-status {
  margin: 2rem 0;
}

.status-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
}

.pulse-ring {
  width: 20px;
  height: 20px;
  border: 3px solid var(--primary-color);
  border-radius: 50%;
  animation: pulse 2s ease-out infinite;
}

.status-text {
  font-weight: 600;
  color: var(--primary-color);
}

.timer-section {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 1.5rem;
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
  border-radius: 1rem;
  margin: 2rem 0;
}

.timer-icon {
  font-size: 2rem;
}

.timer-value {
  font-size: 2rem;
  font-weight: 800;
  color: var(--warning-color);
}

.countries-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 1rem;
  margin: 2rem 0;
}

.country-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 1.25rem 1rem;
  border: 2px solid rgba(102, 126, 234, 0.15);
  border-radius: 1rem;
  cursor: pointer;
  transition: all 0.3s ease;
  background: rgba(102, 126, 234, 0.02);
  text-align: center;
}

.country-card:hover {
  border-color: var(--primary-color);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
  transform: translateY(-2px);
}

.country-card.selected {
  border-color: var(--primary-color);
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
}

.country-flag {
  font-size: 3rem;
}

.country-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text-color);
}

.input-error {
  border-color: var(--danger-color) !important;
}

.form-error {
  display: block;
  margin-top: 0.375rem;
  font-size: 0.75rem;
  color: var(--danger-color);
  font-style: italic;
}

.btn-back {
  background: none;
  border: none;
  color: var(--primary-color);
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  margin-bottom: 1rem;
  padding: 0.5rem;
  transition: all 0.2s;
}

.btn-back:hover {
  transform: translateX(-4px);
}

.subtitle {
  color: var(--text-light);
  text-align: center;
  margin-bottom: 2rem;
}

.amount-display-small {
  text-align: center;
  padding: 1rem;
  background: rgba(102, 126, 234, 0.1);
  border-radius: 0.75rem;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--primary-color);
  margin-bottom: 2rem;
}

.contacts-info {
  padding: 1rem;
  background: rgba(102, 126, 234, 0.05);
  border-radius: 0.75rem;
  margin-top: 1rem;
  font-size: 0.875rem;
  color: var(--text-light);
}

.contacts-info div {
  margin: 0.25rem 0;
}

.info-message {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.1) 100%);
  border-left: 4px solid var(--info-color);
  padding: 1rem;
  border-radius: 0.75rem;
  margin-bottom: 1.5rem;
}

.info-message p {
  margin: 0;
  color: var(--text-color);
  font-size: 0.875rem;
}

.payment-mismatch-warning {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.15) 100%);
  border: 2px solid rgba(239, 68, 68, 0.4);
  border-radius: 1rem;
  padding: 1.5rem;
  margin-bottom: 2rem;
  animation: shake 0.5s ease-in-out;
}

.mismatch-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.mismatch-icon {
  font-size: 2rem;
  animation: pulse-warning 2s infinite;
}

.mismatch-header h3 {
  margin: 0;
  color: #dc2626;
  font-size: 1.125rem;
  font-weight: 700;
}

.mismatch-text {
  color: var(--text-color);
  line-height: 1.6;
  margin: 0;
}

.mismatch-text strong {
  color: #dc2626;
}

@keyframes pulse-warning {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.1);
  }
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
  20%, 40%, 60%, 80% { transform: translateX(5px); }
}

.payment-footer {
  text-align: center;
  color: white;
  margin-top: 2rem;
  opacity: 0.9;
}

.payment-footer p {
  margin: 0.5rem 0;
}

.powered-by {
  font-size: 0.875rem;
  opacity: 0.8;
}

.payment-details {
  margin-top: 2rem;
  padding: 1.5rem;
  background: rgba(102, 126, 234, 0.05);
  border-radius: 1rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem 0;
  border-bottom: 1px solid rgba(102, 126, 234, 0.1);
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-row.highlight {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
  padding: 1rem;
  border-radius: 0.5rem;
  margin-top: 0.5rem;
}

.shortage-amount {
  color: #d97706;
  font-size: 1.25rem;
}

.warning-header {
  text-align: center;
  margin-bottom: 2rem;
}

.warning-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
  animation: pulse-warning 2s infinite;
}

.warning-header h2 {
  color: #d97706;
  margin: 0;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes pulse {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.7);
  }
  
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 10px rgba(102, 126, 234, 0);
  }
  
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
  }
}

/* Responsive */
@media (max-width: 768px) {
  .payment-page {
    padding: 1rem 0.5rem;
  }
  
  .payment-card {
    padding: 1.5rem;
  }
  
  .amount-value {
    font-size: 2rem;
  }
  
  .qr-image {
    width: 200px;
    height: 200px;
  }
}
</style>

