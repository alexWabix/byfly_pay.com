# 📚 ByFly Payment Center - API Documentation

Полная документация по REST API платежного центра ByFly Travel

---

## 🔑 Авторизация

API использует два типа авторизации:

### 1. API Token (для публичных endpoints)
Добавьте заголовок к каждому запросу:
```
X-API-Token: your_64_character_token_here
```

### 2. JWT Bearer Token (для админских endpoints)
```
Authorization: Bearer your_jwt_token_here
```

---

## 📋 Содержание

1. [Авторизация админов](#авторизация-админов)
2. [Создание и управление платежами](#создание-и-управление-платежами)
3. [Работа с Kaspi терминалами](#работа-с-kaspi-терминалами)
4. [Админские endpoints](#админские-endpoints)
5. [Webhook уведомления](#webhook-уведомления)
6. [Коды ошибок](#коды-ошибок)

---

## 🔐 Авторизация админов

### Отправка SMS кода

**Endpoint:** `POST /api/auth/send-code`

**Request:**
```json
{
  "phone": "+77780021666"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "SMS код отправлен",
  "data": null
}
```

**Example (cURL):**
```bash
curl -X POST https://byfly-pay.com/api/auth/send-code \
  -H "Content-Type: application/json" \
  -d '{"phone":"+77780021666"}'
```

---

### Проверка кода и получение токена

**Endpoint:** `POST /api/auth/verify`

**Request:**
```json
{
  "phone": "+77780021666",
  "code": "123456"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Авторизация успешна",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_at": 1703521234,
    "admin": {
      "id": 1,
      "phone": "+77780021666",
      "name": "Super Admin",
      "country_code": "+7",
      "country_name": "Казахстан",
      "is_super_admin": true,
      "allowed_countries": ["Казахстан", "Узбекистан"],
      "allowed_payment_systems": null
    }
  }
}
```

**Example (JavaScript):**
```javascript
const response = await fetch('https://byfly-pay.com/api/auth/verify', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    phone: '+77780021666',
    code: '123456'
  })
})

const data = await response.json()
if (data.success) {
  localStorage.setItem('token', data.data.token)
  console.log('Авторизован как:', data.data.admin.name)
}
```

---

## 💳 Создание и управление платежами

### Создание платежа

**Endpoint:** `POST /api/payment/init`  
**Auth:** `X-API-Token`

**Request:**
```json
{
  "amount": 10000,
  "currency": "KZT",
  "description": "Оплата за тур в Дубай",
  "customer_phone": "+77001234567",
  "customer_email": "client@example.com",
  "customer_name": "Иванов Иван",
  "payment_method_id": 1,
  "webhook_url": "https://your-site.com/webhook/payment",
  "metadata": {
    "order_id": "12345",
    "tour_id": "DXB-001",
    "custom_field": "any data"
  }
}
```

**Параметры:**
| Параметр | Тип | Обязательный | Описание |
|----------|-----|--------------|----------|
| `amount` | number | ✅ | Сумма в тиынах (для KZT) |
| `currency` | string | ❌ | Валюта (по умолчанию KZT) |
| `description` | string | ❌ | Описание платежа |
| `customer_phone` | string | ❌ | Телефон клиента |
| `customer_email` | string | ❌ | Email клиента |
| `customer_name` | string | ❌ | Имя клиента |
| `payment_method_id` | number | ❌ | ID способа оплаты |
| `webhook_url` | string | ❌ | URL для webhook уведомления |
| `metadata` | object | ❌ | Произвольные данные |

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Платеж создан",
  "data": {
    "transaction_id": "ABC123DEF456789...",
    "amount": 10000,
    "currency": "KZT",
    "status": "pending",
    "expires_at": "2025-12-24 15:30:00"
  }
}
```

**Example (PHP):**
```php
$ch = curl_init('https://byfly-pay.com/api/payment/init');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'amount' => 10000,
    'description' => 'Оплата за тур',
    'customer_phone' => '+77001234567',
    'webhook_url' => 'https://my-site.com/webhook'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-Token: your_api_token_here'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$data = json_decode($response, true);

if ($data['success']) {
    $transactionId = $data['data']['transaction_id'];
    echo "Платеж создан: $transactionId\n";
}
```

---

### Обработка через Kaspi

**Endpoint:** `POST /api/payment/{transaction_id}/kaspi`  
**Auth:** `X-API-Token`

**Request:**
```json
{
  "payment_method": "kaspi_gold"
}
```

**Доступные способы:**
- `kaspi_gold` - Kaspi Gold (1% комиссия)
- `kaspi_red` - Kaspi Red (1% комиссия)
- `kaspi_credit` - Kaspi Кредит (14% комиссия)
- `kaspi_installment_12` - Рассрочка 12 мес. (14%)
- `kaspi_installment_24` - Рассрочка 24 мес. (14%)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Платеж инициирован",
  "data": {
    "transaction_id": "ABC123DEF456789...",
    "amount": 10100,
    "original_amount": 10000,
    "commission_amount": 100,
    "commission_percent": 1,
    "payment_url": "https://pay.kaspi.kz/pay/...",
    "qr_code": "https://qr.kaspi.kz/...",
    "terminal_id": 3
  }
}
```

**Example (Python):**
```python
import requests

headers = {
    'Content-Type': 'application/json',
    'X-API-Token': 'your_api_token_here'
}

data = {
    'payment_method': 'kaspi_gold'
}

response = requests.post(
    'https://byfly-pay.com/api/payment/ABC123/kaspi',
    headers=headers,
    json=data
)

result = response.json()
if result['success']:
    payment_url = result['data']['payment_url']
    print(f"Отправьте клиенту ссылку: {payment_url}")
```

---

### Проверка статуса платежа

**Endpoint:** `GET /api/payment/{transaction_id}/status`  
**Auth:** `X-API-Token`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "transaction_id": "ABC123DEF456789...",
    "status": "paid",
    "amount": 10100,
    "paid_amount": 10100,
    "remaining_amount": 0
  }
}
```

**Статусы платежа:**
| Статус | Описание |
|--------|----------|
| `pending` | Ожидание оплаты |
| `processing` | В обработке на терминале |
| `paid` | Полностью оплачено |
| `partially_paid` | Частично оплачено |
| `cancelled` | Отменено |
| `failed` | Ошибка |

**Example (Node.js):**
```javascript
const axios = require('axios')

async function checkPayment(transactionId) {
  const response = await axios.get(
    `https://byfly-pay.com/api/payment/${transactionId}/status`,
    {
      headers: {
        'X-API-Token': 'your_api_token_here'
      }
    }
  )
  
  return response.data.data.status
}

// Проверяем статус каждую секунду
const interval = setInterval(async () => {
  const status = await checkPayment('ABC123')
  console.log('Payment status:', status)
  
  if (status === 'paid') {
    clearInterval(interval)
    console.log('Платеж завершен!')
  }
}, 1000)
```

---

### Получение информации о платеже

**Endpoint:** `GET /api/payment/{transaction_id}`  
**Auth:** `X-API-Token`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "transaction_id": "ABC123DEF456789...",
    "amount": 10100,
    "currency": "KZT",
    "status": "paid",
    "description": "Оплата за тур в Дубай",
    "payment_url": "https://pay.kaspi.kz/pay/...",
    "qr_code": "https://qr.kaspi.kz/...",
    "paid_amount": 10100,
    "remaining_amount": 0,
    "created_at": "2025-12-24 10:00:00",
    "paid_at": "2025-12-24 10:05:30"
  }
}
```

---

### Отмена платежа

**Endpoint:** `POST /api/payment/{transaction_id}/cancel`  
**Auth:** `X-API-Token`

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Платеж отменен",
  "data": null
}
```

---

## 📱 Webhook уведомления

После успешной оплаты система отправит POST запрос на указанный `webhook_url`.

### Структура webhook:

**Request:**
```http
POST https://your-site.com/webhook/payment
Content-Type: application/json
X-Webhook-Signature: sha256_hmac_signature
```

**Body:**
```json
{
  "transaction_id": "ABC123DEF456789...",
  "status": "paid",
  "amount": 10100,
  "paid_amount": 10100,
  "currency": "KZT",
  "description": "Оплата за тур",
  "metadata": {
    "order_id": "12345",
    "tour_id": "DXB-001"
  },
  "paid_at": "2025-12-24 10:05:30"
}
```

### Проверка подписи webhook:

**PHP:**
```php
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$body = file_get_contents('php://input');
$expectedSignature = hash_hmac('sha256', $body, 'your_jwt_secret');

if (!hash_equals($signature, $expectedSignature)) {
    http_response_code(401);
    die('Invalid signature');
}

$data = json_decode($body, true);
// Обрабатываем платеж
```

**Node.js:**
```javascript
const crypto = require('crypto')

function verifyWebhook(req, secret) {
  const signature = req.headers['x-webhook-signature']
  const body = JSON.stringify(req.body)
  const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(body)
    .digest('hex')
  
  return signature === expectedSignature
}
```

---

## 🛠️ Админские Endpoints

### Получение списка транзакций

**Endpoint:** `GET /api/admin/transactions`  
**Auth:** `Bearer Token`

**Query параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `source_id` | number | Фильтр по источнику |
| `status` | string | Фильтр по статусу |
| `date_from` | string | Дата от (YYYY-MM-DD) |
| `date_to` | string | Дата до (YYYY-MM-DD) |
| `limit` | number | Лимит записей (по умолчанию 100) |
| `offset` | number | Смещение (для пагинации) |

**Example:**
```bash
curl https://byfly-pay.com/api/admin/transactions?status=paid&limit=50 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

---

### Управление источниками

#### Получить список
```http
GET /api/admin/sources
Authorization: Bearer {token}
```

#### Создать источник
```http
POST /api/admin/sources
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Мой сайт",
  "type": "website",
  "description": "Основной сайт компании",
  "is_active": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Источник создан",
  "data": {
    "id": 1,
    "api_token": "a1b2c3d4e5f6...64_characters_total"
  }
}
```

⚠️ **ВАЖНО:** Сохраните `api_token` - он больше не будет показан!

#### Обновить источник
```http
PUT /api/admin/sources/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Новое название",
  "is_active": false
}
```

#### Удалить источник
```http
DELETE /api/admin/sources/{id}
Authorization: Bearer {token}
```

---

### Управление терминалами Kaspi

#### Список терминалов
```http
GET /api/admin/terminals
Authorization: Bearer {token}
```

#### Добавить терминал
```http
POST /api/admin/terminals
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Терминал 1",
  "ip_address": "http://109.175.215.40",
  "port": 130,
  "camera_id": 4,
  "camera_url": "http://109.175.215.40:3000/qr/4",
  "is_active": true
}
```

#### Проверить статус терминала
```http
GET /api/admin/terminals/{id}/status
Authorization: Bearer {token}
```

---

## 💰 Расчет комиссий

Комиссии добавляются к сумме если `add_commission_to_amount = true`:

**Kaspi Gold:**
```
Сумма к получению: 10,000 ₸
Комиссия (1%): 100 ₸
К оплате клиентом: 10,100 ₸
```

**Kaspi Кредит/Рассрочка:**
```
Сумма к получению: 10,000 ₸
Комиссия (14%): 1,400 ₸
К оплате клиентом: 11,400 ₸
```

**Формула:**
```javascript
totalAmount = amount + (amount * (commissionPercent / 100))
```

---

## 🔄 Полный пример интеграции

### Шаг 1: Создание платежа

```javascript
async function createPayment() {
  const response = await fetch('https://byfly-pay.com/api/payment/init', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-API-Token': 'YOUR_API_TOKEN'
    },
    body: JSON.stringify({
      amount: 10000,
      description: 'Оплата заказа #12345',
      customer_phone: '+77001234567',
      webhook_url: 'https://my-site.com/webhook'
    })
  })
  
  const data = await response.json()
  return data.data.transaction_id
}
```

### Шаг 2: Инициация через Kaspi

```javascript
async function initiateKaspiPayment(transactionId) {
  const response = await fetch(
    `https://byfly-pay.com/api/payment/${transactionId}/kaspi`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Token': 'YOUR_API_TOKEN'
      },
      body: JSON.stringify({
        payment_method: 'kaspi_gold'
      })
    }
  )
  
  const data = await response.json()
  return data.data
}
```

### Шаг 3: Показать QR клиенту

```javascript
async function showPaymentToCustomer() {
  // 1. Создаем платеж
  const transactionId = await createPayment()
  console.log('Transaction created:', transactionId)
  
  // 2. Инициируем через Kaspi
  const kaspiData = await initiateKaspiPayment(transactionId)
  console.log('Payment URL:', kaspiData.payment_url)
  console.log('QR Code:', kaspiData.qr_code)
  
  // 3. Показываем QR клиенту
  document.getElementById('qr-code').src = kaspiData.qr_code
  document.getElementById('payment-link').href = kaspiData.payment_url
  
  // 4. Запускаем мониторинг
  const checkInterval = setInterval(async () => {
    const status = await checkStatus(transactionId)
    
    if (status === 'paid') {
      clearInterval(checkInterval)
      alert('Платеж успешно завершен!')
    }
  }, 1000)
}
```

### Шаг 4: Обработка webhook

```javascript
// Express.js example
app.post('/webhook/payment', (req, res) => {
  const signature = req.headers['x-webhook-signature']
  const body = JSON.stringify(req.body)
  
  // Проверяем подпись
  const crypto = require('crypto')
  const expectedSignature = crypto
    .createHmac('sha256', 'YOUR_JWT_SECRET')
    .update(body)
    .digest('hex')
  
  if (signature !== expectedSignature) {
    return res.status(401).send('Invalid signature')
  }
  
  const { transaction_id, status, amount, metadata } = req.body
  
  if (status === 'paid') {
    // Обрабатываем успешный платеж
    console.log(`Order ${metadata.order_id} paid: ${amount}`)
    
    // Обновляем статус заказа в вашей БД
    updateOrder(metadata.order_id, 'paid')
  }
  
  res.status(200).send('OK')
})
```

---

## ⚠️ Коды ошибок

| Код | Описание |
|-----|----------|
| `200` | Успешно |
| `201` | Создано |
| `400` | Неверный запрос |
| `401` | Требуется авторизация |
| `403` | Доступ запрещен |
| `404` | Не найдено |
| `500` | Внутренняя ошибка сервера |

**Формат ошибки:**
```json
{
  "success": false,
  "message": "Номер телефона обязателен",
  "errors": {
    "phone": "Field is required"
  }
}
```

---

## 🎯 Best Practices

### 1. Хранение API токенов
```javascript
// ❌ Плохо - токен в коде
const API_TOKEN = 'a1b2c3d4...'

// ✅ Хорошо - токен в переменных окружения
const API_TOKEN = process.env.BYFLY_API_TOKEN
```

### 2. Обработка ошибок
```javascript
try {
  const response = await createPayment()
} catch (error) {
  if (error.response?.status === 401) {
    console.error('Invalid API token')
  } else if (error.response?.status === 400) {
    console.error('Bad request:', error.response.data.message)
  } else {
    console.error('Unknown error:', error)
  }
}
```

### 3. Мониторинг статуса
```javascript
// Проверяем не чаще 1 раза в секунду
const checkInterval = setInterval(checkStatus, 1000)

// Обязательно останавливаем через 5 минут
setTimeout(() => {
  clearInterval(checkInterval)
}, 300000)
```

### 4. Webhook должен отвечать быстро
```php
// ✅ Хорошо - быстрый ответ
http_response_code(200);
echo 'OK';

// Обработка в фоне
register_shutdown_function(function() use ($data) {
    processPayment($data);
});
```

---

## 📞 Поддержка

**Email:** support@byfly.kz  
**Telegram:** @byfly_support  
**Документация:** https://byfly-pay.com/docs

---

**© 2025 ByFly Travel Payment Center**


