<template>
  <div class="api-docs-page">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">📚 Документация API</h3>
        <p class="subtitle">Полное руководство по интеграции</p>
      </div>

      <!-- API Тестер -->
      <div class="api-tester">
        <h4>🧪 Тестер API</h4>
        <div class="tester-controls">
          <select v-model="selectedSource" class="form-select">
            <option :value="null" disabled>Выберите источник</option>
            <option v-for="source in sources" :key="source.id" :value="source">
              {{ source.name }} ({{ source.api_token.substring(0, 16) }}...)
            </option>
          </select>
          
          <select v-model="testMethod" class="form-select">
            <option value="GET">GET</option>
            <option value="POST">POST</option>
          </select>
          
          <input v-model="testEndpoint" type="text" class="form-control" placeholder="/payment/init" />
          
          <textarea v-if="testMethod === 'POST'" v-model="testBody" class="form-control" rows="3" placeholder='{"amount": 500}'></textarea>
          
          <div class="tester-buttons">
            <button @click="testApiRequest" class="btn btn-primary" :disabled="testing || !selectedSource">
              <span v-if="testing">Отправка...</span>
              <span v-else>🚀 Отправить запрос</span>
            </button>
            <button @click="copyCurl" class="btn btn-outline" :disabled="!selectedSource || !testEndpoint">
              📋 Копировать CURL
            </button>
          </div>
        </div>
        
        <!-- Результат -->
        <div v-if="testResult" class="test-result">
          <h5>Ответ (HTTP {{ testResult.status }}):</h5>
          <pre class="code">{{ JSON.stringify(testResult.data, null, 2) }}</pre>
        </div>
        
        <!-- CURL код -->
        <div v-if="curlCode" class="curl-code">
          <h5>CURL команда:</h5>
          <pre class="code">{{ curlCode }}</pre>
        </div>
      </div>

      <!-- Вкладки -->
      <div class="tabs">
        <button @click="activeTab = 'start'" class="tab" :class="{ active: activeTab === 'start' }">🚀 Быстрый старт</button>
        <button @click="activeTab = 'payments'" class="tab" :class="{ active: activeTab === 'payments' }">💳 Платежи</button>
        <button @click="activeTab = 'transactions'" class="tab" :class="{ active: activeTab === 'transactions' }">📊 Транзакции</button>
        <button @click="activeTab = 'data'" class="tab" :class="{ active: activeTab === 'data' }">🗂️ Справочники</button>
        <button @click="activeTab = 'statistics'" class="tab" :class="{ active: activeTab === 'statistics' }">📈 Статистика</button>
        <button @click="activeTab = 'webhooks'" class="tab" :class="{ active: activeTab === 'webhooks' }">🔔 Вебхуки</button>
        <button @click="activeTab = 'examples'" class="tab" :class="{ active: activeTab === 'examples' }">💻 Примеры</button>
      </div>

      <div class="docs-content">
        
        <!-- Быстрый старт -->
        <div v-show="activeTab === 'start'" class="tab-content">
          <h2>🚀 Быстрый старт</h2>
          <p><strong>ByFly Payment Center</strong> - платежный шлюз для приема оплат через Kaspi терминалы</p>
          
          <div class="step-box">
            <h3>1. Получите API токен</h3>
            <p>Создайте источник в разделе <router-link to="/sources">Источники API</router-link></p>
          </div>
          
          <div class="step-box">
            <h3>2. Создайте платеж</h3>
            <pre class="code">POST {{ apiBaseUrl }}/payment/init
{
  "amount": 5000,
  "description": "Заказ #123",
  "webhook_url": "https://your-site.com/webhook",
  "metadata": {"order_id": "123"}
}</pre>
          </div>
          
          <div class="step-box">
            <h3>3. Отправьте клиенту payment_url</h3>
            <p>Клиент оплачивает → Вы получаете вебхук</p>
          </div>
        </div>

        <!-- Платежи -->
        <div v-show="activeTab === 'payments'" class="tab-content">
          <h2>💳 API Платежей</h2>
          
          <div class="endpoint">
            <div class="endpoint-header">
              <div>
                <span class="method-post">POST</span>
                <code>/api/payment/init</code>
              </div>
              <button @click="setTestEndpoint('POST', '/payment/init', {amount: 500, description: 'Тест'})" class="btn btn-sm btn-outline">
                🧪 Попробовать
              </button>
            </div>
            <p class="endpoint-desc"><strong>Создание нового платежа</strong></p>
            
            <h4>Параметры запроса:</h4>
            <table class="params-table">
              <tr>
                <th>Параметр</th>
                <th>Тип</th>
                <th>Обяз.</th>
                <th>Описание</th>
              </tr>
              <tr>
                <td><code>amount</code></td>
                <td>number</td>
                <td>✅</td>
                <td>Сумма к получению (чистыми)</td>
              </tr>
              <tr>
                <td><code>currency</code></td>
                <td>string</td>
                <td>❌</td>
                <td>Валюта (по умолчанию KZT)</td>
              </tr>
              <tr>
                <td><code>description</code></td>
                <td>string</td>
                <td>❌</td>
                <td>Описание (видно клиенту)</td>
              </tr>
              <tr>
                <td><code>webhook_url</code></td>
                <td>string</td>
                <td>❌</td>
                <td>URL для вебхуков</td>
              </tr>
              <tr>
                <td><code>webhook_secret</code></td>
                <td>string</td>
                <td>❌</td>
                <td>Секрет для HMAC подписи</td>
              </tr>
              <tr>
                <td><code>metadata</code></td>
                <td>object</td>
                <td>❌</td>
                <td>Custom поля (user_id, order_id...)</td>
              </tr>
            </table>
            
            <h4>Пример запроса:</h4>
            <pre class="code">{
  "amount": 5000,
  "currency": "KZT",
  "description": "Оплата заказа #123",
  "webhook_url": "https://your-site.com/webhook",
  "webhook_secret": "your_secret_key",
  "metadata": {
    "user_id": "12345",
    "order_id": "ORD-001"
  }
}</pre>

            <h4>Пример ответа (Success):</h4>
            <pre class="code">{
  "success": true,
  "message": "Платеж создан",
  "data": {
    "transaction_id": "9D6973B7005E1FF1D93B87FDB12D8C71",
    "payment_url": "https://byfly-pay.com/pay/9D6973B7...",
    "amount": 5000,
    "currency": "KZT",
    "status": "pending",
    "expires_at": "2025-12-27 16:00:00"
  }
}</pre>
          </div>
          
          <div class="endpoint">
            <div class="endpoint-header">
              <div>
                <span class="method-get">GET</span>
                <code>/api/payment/{transaction_id}</code>
              </div>
              <button @click="setTestEndpoint('GET', '/payment/9D6973B7005E1FF1D93B87FDB12D8C71')" class="btn btn-sm btn-outline">
                🧪 Попробовать
              </button>
            </div>
            <p class="endpoint-desc"><strong>Получение полной информации о платеже</strong></p>
            <p>Возвращает все данные о транзакции включая статус, суммы, даты и метаданные. <strong>Публичный endpoint</strong> - не требует токен.</p>
            
            <h4>URL параметры:</h4>
            <table class="params-table">
              <tr>
                <th>Параметр</th>
                <th>Описание</th>
              </tr>
              <tr>
                <td><code>transaction_id</code></td>
                <td>ID транзакции (32 символа)</td>
              </tr>
            </table>

            <h4>Пример запроса:</h4>
            <pre class="code">GET /api/payment/9D6973B7005E1FF1D93B87FDB12D8C71</pre>

            <h4>Пример ответа (Success):</h4>
            <pre class="code">{
  "success": true,
  "data": {
    "transaction_id": "9D6973B7005E1FF1D93B87FDB12D8C71",
    "amount": 5000,
    "original_amount": 5000,
    "paid_amount": 5050,
    "actual_amount_received": 5000,
    "remaining_amount": 0,
    "currency": "KZT",
    "status": "paid",
    "description": "Оплата заказа #123",
    "payment_url": "https://pay.kaspi.kz/pay/...",
    "qr_code": "https://qr.kaspi.kz/...",
    "created_at": "2025-12-26 15:00:00",
    "paid_at": "2025-12-26 15:05:30",
    "expires_at": "2025-12-27 15:00:00",
    "needs_additional_payment": false
  }
}</pre>

            <h4>Возможные статусы:</h4>
            <ul>
              <li><code>pending</code> - Ожидает оплаты (клиент не открыл страницу)</li>
              <li><code>processing</code> - В процессе (клиент сканирует QR)</li>
              <li><code>paid</code> - Полностью оплачено ✅</li>
              <li><code>partially_paid</code> - Частично оплачено (нужна доплата)</li>
              <li><code>cancelled</code> - Отменено</li>
              <li><code>failed</code> - Ошибка</li>
            </ul>
          </div>
          
          <div class="endpoint">
            <div class="endpoint-header">
              <div>
                <span class="method-get">GET</span>
                <code>/api/payment/{transaction_id}/status</code>
              </div>
              <button @click="setTestEndpoint('GET', '/payment/9D6973B7005E1FF1D93B87FDB12D8C71/status')" class="btn btn-sm btn-outline">
                🧪 Попробовать
              </button>
            </div>
            <p class="endpoint-desc"><strong>Быстрая проверка статуса платежа</strong></p>
            <p>Возвращает минимальную информацию - только статус и основные суммы. <strong>Публичный endpoint</strong> - не требует токен.</p>
            <p>💡 <em>Используйте этот endpoint для частой проверки статуса (каждую секунду), так как он быстрее чем полный GET /api/payment/{id}</em></p>
            
            <h4>Пример запроса:</h4>
            <pre class="code">GET /api/payment/9D6973B7005E1FF1D93B87FDB12D8C71/status</pre>

            <h4>Пример ответа (Success):</h4>
            <pre class="code">{
  "success": true,
  "data": {
    "transaction_id": "9D6973B7005E1FF1D93B87FDB12D8C71",
    "status": "paid",
    "amount": 5000,
    "paid_amount": 5050,
    "actual_amount_received": 5000,
    "remaining_amount": 0,
    "needs_additional_payment": false
  }
}</pre>

            <h4>Когда использовать:</h4>
            <ul>
              <li>✅ Частая проверка статуса (polling каждую секунду)</li>
              <li>✅ Проверка завершения оплаты</li>
              <li>✅ Мониторинг в реальном времени</li>
              <li>❌ Для получения полных данных используйте GET /api/payment/{id}</li>
            </ul>
          </div>
        </div>

        <!-- Транзакции -->
        <div v-show="activeTab === 'transactions'" class="tab-content">
          <h2>📊 API Транзакций</h2>
          
          <div class="endpoint">
            <div class="endpoint-header">
              <div>
                <span class="method-get">GET</span>
                <code>/api/transactions</code>
              </div>
              <button @click="setTestEndpoint('GET', '/transactions?status=paid&limit=10')" class="btn btn-sm btn-outline">
                🧪 Попробовать
              </button>
            </div>
            <p class="endpoint-desc"><strong>История транзакций с фильтрами</strong></p>
            
            <h4>Query параметры:</h4>
            <table class="params-table">
              <tr>
                <th>Параметр</th>
                <th>Тип</th>
                <th>Описание</th>
              </tr>
              <tr>
                <td><code>status</code></td>
                <td>string</td>
                <td>paid, pending, partially_paid, cancelled, failed</td>
              </tr>
              <tr>
                <td><code>date_from</code></td>
                <td>date</td>
                <td>Дата от (YYYY-MM-DD)</td>
              </tr>
              <tr>
                <td><code>date_to</code></td>
                <td>date</td>
                <td>Дата до (YYYY-MM-DD)</td>
              </tr>
              <tr>
                <td><code>amount_from</code></td>
                <td>number</td>
                <td>Сумма от</td>
              </tr>
              <tr>
                <td><code>amount_to</code></td>
                <td>number</td>
                <td>Сумма до</td>
              </tr>
              <tr>
                <td><code>limit</code></td>
                <td>number</td>
                <td>Количество записей (макс 1000, по умолчанию 100)</td>
              </tr>
              <tr>
                <td><code>offset</code></td>
                <td>number</td>
                <td>Смещение для пагинации</td>
              </tr>
            </table>

            <h4>Пример запроса:</h4>
            <pre class="code">GET /api/transactions?status=paid&date_from=2025-12-01&limit=50</pre>

            <h4>Пример ответа:</h4>
            <pre class="code">{
  "success": true,
  "data": {
    "transactions": [
      {
        "transaction_id": "9D6973B7...",
        "amount": 5000,
        "paid_amount": 5050,
        "actual_amount_received": 5000,
        "status": "paid",
        "metadata": {"order_id": "123"}
      }
    ],
    "total": 150,
    "limit": 50,
    "offset": 0
  }
}</pre>
          </div>
          
          <div class="endpoint">
            <div class="endpoint-header">
              <div>
                <span class="method-get">GET</span>
                <code>/api/transaction/{transaction_id}</code>
              </div>
              <button @click="setTestEndpoint('GET', '/transaction/9D6973B7005E1FF1D93B87FDB12D8C71')" class="btn btn-sm btn-outline">
                🧪 Попробовать
              </button>
            </div>
            <p class="endpoint-desc"><strong>Детали конкретной транзакции</strong></p>
            <p>Возвращает полную информацию включая историю частичных оплат и логи вебхуков</p>
            
            <h4>Пример ответа:</h4>
            <pre class="code">{
  "success": true,
  "data": {
    "transaction": {
      "transaction_id": "9D6973B7...",
      "amount": 5000,
      "metadata": {"order_id": "123"}
    },
    "partial_payments": [
      {
        "amount": 5050,
        "commission_amount": 50.5,
        "is_refunded": false
      }
    ],
    "webhook_logs": [
      {
        "event_type": "paid",
        "is_success": true
      }
    ]
  }
}</pre>
          </div>
        </div>

        <!-- Справочники -->
        <div v-show="activeTab === 'data'" class="tab-content">
          <h2>🗂️ API Справочников</h2>
          
          <div class="endpoint">
            <div class="endpoint-header">
              <div>
                <span class="method-get">GET</span>
                <code>/api/countries</code>
              </div>
              <button @click="setTestEndpoint('GET', '/countries')" class="btn btn-sm btn-outline">
                🧪 Попробовать
              </button>
            </div>
            <p class="endpoint-desc"><strong>Список доступных стран для оплаты</strong></p>
            <pre class="code">{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "KZ",
      "name": "Казахстан",
      "currency_code": "KZT",
      "currency_symbol": "₸",
      "flag_emoji": "🇰🇿",
      "phone_code": "+7",
      "phone_mask": "+7 (###) ###-##-##"
    }
  ]
}</pre>
          </div>
          
          <div class="endpoint">
            <div class="endpoint-header">
              <div>
                <span class="method-get">GET</span>
                <code>/api/payment-methods</code>
              </div>
              <button @click="setTestEndpoint('GET', '/payment-methods')" class="btn btn-sm btn-outline">
                🧪 Попробовать
              </button>
            </div>
            <p class="endpoint-desc"><strong>Способы оплаты с комиссиями</strong></p>
            <pre class="code">{
  "success": true,
  "data": [
    {
      "code": "kaspi_gold",
      "name": "Kaspi Gold",
      "commission_percent": 1.0,
      "has_credit": false
    },
    {
      "code": "kaspi_credit",
      "name": "Kaspi Кредит",
      "commission_percent": 0,
      "has_credit": true,
      "credit_commission_percent": 14.0
    }
  ]
}</pre>
          </div>
          
          <div class="endpoint">
            <div class="endpoint-header">
              <div>
                <span class="method-get">GET</span>
                <code>/api/exchange-rates</code>
              </div>
              <button @click="setTestEndpoint('GET', '/exchange-rates')" class="btn btn-sm btn-outline">
                🧪 Попробовать
              </button>
            </div>
            <p class="endpoint-desc"><strong>Актуальные курсы валют</strong></p>
            <pre class="code">{
  "success": true,
  "data": [
    {
      "from_currency": "KZT",
      "to_currency": "USD",
      "rate": 0.0021,
      "updated_at": "2025-12-26 12:00:00"
    }
  ]
}</pre>
          </div>
          
          <div class="endpoint">
            <div class="endpoint-header">
              <div>
                <span class="method-get">GET</span>
                <code>/api/terminals</code>
              </div>
              <button @click="setTestEndpoint('GET', '/terminals')" class="btn btn-sm btn-outline">
                🧪 Попробовать
              </button>
            </div>
            <p class="endpoint-desc"><strong>Статусы терминалов Kaspi</strong></p>
            <pre class="code">{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Terminal 1",
      "status": "online",
      "is_busy": false,
      "is_active": true
    }
  ]
}</pre>
          </div>
        </div>

        <!-- Статистика -->
        <div v-show="activeTab === 'statistics'" class="tab-content">
          <h2>📈 API Статистики</h2>
          
          <div class="endpoint">
            <div class="endpoint-header">
              <div>
                <span class="method-get">GET</span>
                <code>/api/statistics</code>
              </div>
              <button @click="setTestEndpoint('GET', '/statistics?date_from=2025-12-01&date_to=2025-12-26')" class="btn btn-sm btn-outline">
                🧪 Попробовать
              </button>
            </div>
            <p class="endpoint-desc"><strong>Статистика оплат за период</strong></p>
            
            <h4>Параметры:</h4>
            <table class="params-table">
              <tr>
                <th>Параметр</th>
                <th>Описание</th>
                <th>По умолчанию</th>
              </tr>
              <tr>
                <td><code>date_from</code></td>
                <td>Дата от (YYYY-MM-DD)</td>
                <td>-30 дней</td>
              </tr>
              <tr>
                <td><code>date_to</code></td>
                <td>Дата до (YYYY-MM-DD)</td>
                <td>Сегодня</td>
              </tr>
            </table>

            <h4>Пример ответа:</h4>
            <pre class="code">{
  "success": true,
  "data": {
    "period": {"from": "2025-12-01", "to": "2025-12-26"},
    "summary": {
      "total_transactions": 150,
      "paid_count": 120,
      "total_paid_amount": 750500,
      "total_received_amount": 745000,
      "total_commission": 5500,
      "refund_count": 3
    },
    "by_payment_method": [
      {"payment_method": "Kaspi Gold", "count": 80, "total_commission": 4000},
      {"payment_method": "Kaspi Кредит", "count": 30, "total_commission": 42000}
    ],
    "credit_installment": {
      "count": 40,
      "total_amount": 400000,
      "total_commission": 56000
    }
  }
}</pre>
          </div>
        </div>

        <!-- Вебхуки -->
        <div v-show="activeTab === 'webhooks'" class="tab-content">
          <h2>🔔 Вебхуки</h2>
          <p>Система отправляет POST уведомления при изменении статуса</p>
          
          <h3>События:</h3>
          <ul>
            <li><code>paid</code> - платеж оплачен</li>
            <li><code>partially_paid</code> - частичная оплата</li>
            <li><code>cancelled</code> - отмена</li>
            <li><code>refunded</code> - возврат</li>
            <li><code>expired</code> - истечение</li>
          </ul>
          
          <h3>Формат:</h3>
          <pre class="code">POST https://your-site.com/webhook
Headers:
  X-Webhook-Event: paid
  X-Webhook-Signature: abc123...

Body:
{
  "event": "paid",
  "transaction_id": "9D69...",
  "amount": 5000,
  "metadata": {"order_id": "123"},
  "timestamp": 1703598000
}</pre>

          <h3>Проверка подписи (PHP):</h3>
          <pre class="code">$payload = file_get_contents('php://input');
$signature = hash_hmac('sha256', $payload, 'your_secret');

if ($signature === $_SERVER['HTTP_X_WEBHOOK_SIGNATURE']) {
    // OK
}</pre>
        </div>

        <!-- Примеры -->
        <div v-show="activeTab === 'examples'" class="tab-content">
          <h2>💻 Примеры кода</h2>
          
          <h3>PHP (cURL):</h3>
          <pre class="code">$ch = curl_init('{{ apiBaseUrl }}/payment/init');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'amount' => 5000,
    'webhook_url' => 'https://site.com/webhook',
    'metadata' => ['order_id' => '123']
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Token: YOUR_TOKEN'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$result = json_decode($response, true);
$paymentUrl = $result['data']['payment_url'];</pre>

          <h3>JavaScript:</h3>
          <pre class="code">const response = await fetch('{{ apiBaseUrl }}/payment/init', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-Token': 'YOUR_TOKEN'
  },
  body: JSON.stringify({
    amount: 5000,
    webhook_url: 'https://site.com/webhook',
    metadata: {order_id: '123'}
  })
});

const result = await response.json();
window.location.href = result.data.payment_url;</pre>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'

const activeTab = ref('start')
const sources = ref([])
const selectedSource = ref(null)
const testEndpoint = ref('')
const testMethod = ref('GET')
const testBody = ref('{}')
const testParams = ref({})
const testResult = ref(null)
const testing = ref(false)

const tabs = [
  { id: 'start', name: 'Быстрый старт', icon: '🚀' },
  { id: 'payments', name: 'Платежи', icon: '💳' },
  { id: 'transactions', name: 'Транзакции', icon: '📊' },
  { id: 'data', name: 'Справочники', icon: '🗂️' },
  { id: 'statistics', name: 'Статистика', icon: '📈' },
  { id: 'webhooks', name: 'Вебхуки', icon: '🔔' },
  { id: 'examples', name: 'Примеры', icon: '💻' }
]

const apiBaseUrl = window.location.origin + '/api'

const curlCode = computed(() => {
  if (!selectedSource.value || !testEndpoint.value) return ''
  
  const url = apiBaseUrl + testEndpoint.value
  const method = testMethod.value
  const token = selectedSource.value.api_token
  
  let curl = `curl -X ${method} "${url}"`
  curl += ` \\\n  -H "Content-Type: application/json"`
  curl += ` \\\n  -H "X-API-Token: ${token}"`
  
  if (method === 'POST' && testBody.value && testBody.value !== '{}') {
    curl += ` \\\n  -d '${testBody.value}'`
  }
  
  return curl
})

onMounted(async () => {
  await loadSources()
})

async function loadSources() {
  try {
    const response = await api.get('/admin/sources')
    sources.value = response.data || []
    if (sources.value.length > 0) {
      selectedSource.value = sources.value[0]
    }
  } catch (error) {
    console.error('Failed to load sources:', error)
  }
}

async function testApiRequest() {
  if (!selectedSource.value || !testEndpoint.value) {
    alert('Выберите источник и endpoint')
    return
  }
  
  testing.value = true
  testResult.value = null
  
  try {
    const url = testEndpoint.value
    const token = selectedSource.value.api_token
    
    let response
    if (testMethod.value === 'POST') {
      response = await fetch(apiBaseUrl + url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-API-Token': token
        },
        body: testBody.value
      })
    } else {
      response = await fetch(apiBaseUrl + url, {
        headers: {
          'X-API-Token': token
        }
      })
    }
    
    const data = await response.json()
    testResult.value = {
      status: response.status,
      data: data
    }
  } catch (error) {
    testResult.value = {
      status: 'error',
      data: { error: error.message }
    }
  } finally {
    testing.value = false
  }
}

async function copyCurl() {
  try {
    await navigator.clipboard.writeText(curlCode.value)
    alert('✅ CURL код скопирован в буфер обмена!')
  } catch (e) {
    prompt('Скопируйте CURL код:', curlCode.value)
  }
}

function setTestEndpoint(method, endpoint, body = null) {
  testMethod.value = method
  testEndpoint.value = endpoint
  if (body) {
    testBody.value = JSON.stringify(body, null, 2)
  }
}
</script>

<style scoped>
.api-docs-page {
  animation: fadeIn 0.5s ease-out;
}

.subtitle {
  color: var(--text-light);
  margin-top: 0.5rem;
}

.api-tester {
  padding: 1.5rem;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
  border-bottom: 3px solid var(--primary-color);
}

.api-tester h4 {
  margin: 0 0 1rem 0;
  color: var(--text-color);
}

.tester-controls {
  display: grid;
  gap: 1rem;
}

.tester-buttons {
  display: flex;
  gap: 0.5rem;
}

.test-result,
.curl-code {
  margin-top: 1rem;
  padding: 1rem;
  background: white;
  border-radius: 0.75rem;
  border: 2px solid var(--border-color);
}

.test-result h5,
.curl-code h5 {
  margin: 0 0 0.75rem 0;
  color: var(--text-color);
  font-size: 0.875rem;
  font-weight: 700;
}

.tabs {
  display: flex;
  gap: 0.5rem;
  padding: 1rem;
  background: var(--bg-color);
  border-bottom: 2px solid var(--border-color);
  overflow-x: auto;
  flex-wrap: wrap;
}

.tab {
  padding: 0.75rem 1.5rem;
  border: none;
  background: white;
  border-radius: 0.5rem;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--text-color);
  transition: all 0.2s;
  white-space: nowrap;
}

.tab:hover {
  background: rgba(102, 126, 234, 0.1);
}

.tab.active {
  background: var(--bg-gradient);
  color: white;
}

.docs-content {
  padding: 2rem;
  min-height: 400px;
}

.tab-content h2 {
  margin: 0 0 1.5rem 0;
  color: var(--text-color);
}

.tab-content h3 {
  margin: 1.5rem 0 0.75rem 0;
  color: var(--text-color);
}

.tab-content p {
  line-height: 1.6;
  color: var(--text-color);
}

.tab-content ul {
  line-height: 1.8;
}

.step-box {
  padding: 1.5rem;
  margin: 1rem 0;
  background: rgba(102, 126, 234, 0.05);
  border-left: 4px solid var(--primary-color);
  border-radius: 0.5rem;
}

.step-box h3 {
  margin-top: 0;
}

.endpoint {
  padding: 1.5rem;
  margin: 1.5rem 0;
  background: white;
  border: 2px solid var(--border-color);
  border-radius: 0.75rem;
}

.endpoint code {
  font-size: 1rem;
  font-weight: 600;
  color: var(--primary-color);
}

.endpoint-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.endpoint-desc {
  color: var(--text-light);
  margin: 0.5rem 0 1rem 0;
}

.params-table {
  width: 100%;
  border-collapse: collapse;
  margin: 1rem 0;
  font-size: 0.875rem;
}

.params-table th,
.params-table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid var(--border-color);
}

.params-table th {
  background: var(--bg-color);
  font-weight: 700;
}

.params-table code {
  background: var(--bg-color);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.8rem;
}

.method-get,
.method-post {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  font-weight: 700;
  font-size: 0.75rem;
  margin-right: 0.5rem;
  color: white;
}

.method-get {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.method-post {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.code {
  background: #1e1e1e;
  color: #d4d4d4;
  padding: 1rem;
  border-radius: 0.5rem;
  overflow-x: auto;
  font-family: monospace;
  font-size: 0.875rem;
  line-height: 1.6;
  margin: 0.75rem 0;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
