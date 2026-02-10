# API Интеграция ByFly Payment Center
## Полное руководство для внешних систем

**Версия:** 2.0  
**Дата:** 13 января 2026  
**Документ:** Полная инструкция по интеграции с платежной системой

---

## 📋 Содержание

1. [Введение](#введение)
2. [Аутентификация](#аутентификация)
3. [Создание платежа](#создание-платежа)
4. [Получение информации о платеже](#получение-информации-о-платеже)
5. [Способы оплаты](#способы-оплаты)
6. [Kaspi оплата](#kaspi-оплата)
7. [Проверка статуса](#проверка-статуса)
8. [Webhook уведомления](#webhook-уведомления)
9. [Сценарии использования](#сценарии-использования)
10. [Комиссии и расчеты](#комиссии-и-расчеты)
11. [Обработка ошибок](#обработка-ошибок)
12. [Примеры кода](#примеры-кода)

---

## Введение

ByFly Payment Center - универсальная платежная система для приема оплат через Kaspi терминалы с поддержкой:
- 💳 Kaspi Gold (обычная оплата)
- 💳 Kaspi Red (обычная оплата)  
- 💰 Kaspi Кредит (14% комиссия)
- 📅 Kaspi Рассрочка (14% комиссия)
- ✅ Полная оплата
- 🔄 Частичная оплата с доплатой
- 📡 Webhook уведомления

### Базовый URL

```
Production: https://byfly-pay.com/api
Development: http://localhost/api (только для тестирования)
```

### Формат данных

- Все запросы и ответы в формате **JSON**
- Кодировка: **UTF-8**
- Дата/время: **ISO 8601** (`YYYY-MM-DD HH:MM:SS`)
- Суммы: **float/decimal** (округление до 2 знаков)

---

## Аутентификация

### API Token

Для доступа к API необходим уникальный токен источника (source).

**Как получить токен:**
1. Обратитесь к администратору ByFly Travel
2. Токен генерируется автоматически (64 символа)
3. Сохраните токен в безопасном месте

**Использование:**

```http
X-API-Token: YOUR_64_CHAR_API_TOKEN
```

### Публичные эндпоинты

Некоторые эндпоинты доступны БЕЗ токена:
- `GET /payment/{id}` - получение информации о платеже
- `GET /payment/{id}/status` - проверка статуса
- `POST /payment/{id}/kaspi` - инициация Kaspi оплаты  
- `GET /payment-methods/active` - список способов оплаты

---

## Создание платежа

### POST /api/payment/init

Создает новую транзакцию для оплаты.

**Заголовки:**
```http
Content-Type: application/json
X-API-Token: YOUR_API_TOKEN
```

**Тело запроса:**
```json
{
  "amount": 50000,
  "currency": "KZT",
  "description": "Оплата тура в Дубай #12345",
  "customer_name": "Иванов Иван Иванович",
  "customer_phone": "+77001234567",
  "customer_email": "ivan@example.com",
  "payment_method_id": 1,
  "webhook_url": "https://your-system.com/webhooks/payment",
  "webhook_secret": "your_secret_key_for_hmac",
  "metadata": {
    "order_id": "12345",
    "tour_name": "Дубай 7 дней",
    "customer_id": "CU-456",
    "any_custom_field": "custom_value"
  }
}
```

**Параметры:**

| Поле | Тип | Обязательно | Описание |
|------|-----|-------------|----------|
| `amount` | float | ✅ Да | Сумма к оплате (без комиссии) |
| `currency` | string | ❌ Нет | Валюта (по умолчанию: KZT) |
| `description` | string | ❌ Нет | Описание платежа |
| `customer_name` | string | ❌ Нет | ФИО клиента |
| `customer_phone` | string | ❌ Нет | Телефон клиента |
| `customer_email` | string | ❌ Нет | Email клиента |
| `payment_method_id` | int | ❌ Нет | ID способа оплаты |
| `webhook_url` | string | ❌ Нет | URL для webhook уведомлений |
| `webhook_secret` | string | ❌ Нет | Секретный ключ для HMAC подписи |
| `metadata` | object | ❌ Нет | Произвольные данные (вернутся в webhook) |

**Ответ (201 Created):**
```json
{
  "success": true,
  "message": "Платеж создан",
  "data": {
    "transaction_id": "A3F5D9E2B1C8",
    "amount": 50000,
    "currency": "KZT",
    "status": "pending",
    "expires_at": "2026-01-13 15:30:00"
  }
}
```

**Возможные ошибки:**
```json
{
  "success": false,
  "message": "Сумма должна быть больше 0",
  "error_code": 400
}
```

---

## Получение информации о платеже

### GET /api/payment/{transaction_id}

Получить полную информацию о транзакции.

**Публичный эндпоинт** (не требует токена)

**Пример запроса:**
```http
GET https://byfly-pay.com/api/payment/A3F5D9E2B1C8
```

**Ответ (200 OK):**
```json
{
  "success": true,
  "data": {
    "transaction_id": "A3F5D9E2B1C8",
    "amount": 50000,
    "currency": "KZT",
    "status": "pending",
    "description": "Оплата тура в Дубай #12345",
    "payment_url": "https://pay.kaspi.kz/pay/abc123",
    "qr_code": "https://qr.kaspi.kz/abc123",
    "paid_amount": 0,
    "remaining_amount": 50000,
    "created_at": "2026-01-13 14:30:00",
    "paid_at": null
  }
}
```

**Статусы платежа:**

| Статус | Описание |
|--------|----------|
| `pending` | Ожидает оплаты |
| `processing` | Обрабатывается на терминале |
| `paid` | **Полностью оплачено** ✅ |
| `partially_paid` | **Частично оплачено** (требуется доплата) 🔄 |
| `failed` | Ошибка оплаты ❌ |
| `cancelled` | Отменен |
| `expired` | Истек срок действия ⏰ |

---

## Способы оплаты

### GET /api/payment-methods/active

Получить список доступных способов оплаты.

**Публичный эндпоинт**

**Ответ (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "kaspi_gold",
      "name": "Kaspi Gold",
      "type": "card",
      "provider": "kaspi",
      "country_id": 1,
      "payment_currency": "KZT",
      "icon_emoji": "💳",
      "commission_percent": 0,
      "has_credit": false,
      "credit_commission_percent": 0,
      "has_installment": false,
      "installment_months": null,
      "installment_commission_percent": 0,
      "add_commission_to_amount": false
    },
    {
      "id": 3,
      "code": "kaspi_kredit",
      "name": "Kaspi Кредит",
      "type": "credit",
      "provider": "kaspi",
      "country_id": 1,
      "payment_currency": "KZT",
      "icon_emoji": "💰",
      "commission_percent": 0,
      "has_credit": true,
      "credit_commission_percent": 14,
      "has_installment": false,
      "installment_months": null,
      "installment_commission_percent": 0,
      "add_commission_to_amount": true
    },
    {
      "id": 4,
      "code": "kaspi_rassrochka",
      "name": "Kaspi Рассрочка",
      "type": "installment",
      "provider": "kaspi",
      "country_id": 1,
      "payment_currency": "KZT",
      "icon_emoji": "📅",
      "commission_percent": 0,
      "has_credit": false,
      "credit_commission_percent": 0,
      "has_installment": true,
      "installment_months": "3,6,12",
      "installment_commission_percent": 14,
      "add_commission_to_amount": true
    }
  ]
}
```

---

## Kaspi оплата

### POST /api/payment/{transaction_id}/kaspi

Инициировать оплату через Kaspi терминал.

**Публичный эндпоинт**

**Заголовки:**
```http
Content-Type: application/json
```

**Тело запроса:**
```json
{
  "payment_method": "kaspi_gold"
}
```

**Параметры:**

| Поле | Тип | Описание |
|------|-----|----------|
| `payment_method` | string | `kaspi_gold`, `kaspi_red`, `kaspi_kredit`, `kaspi_rassrochka` |

**Ответ (200 OK):**
```json
{
  "success": true,
  "message": "Платеж инициирован",
  "data": {
    "transaction_id": "A3F5D9E2B1C8",
    "amount": 57000,
    "original_amount": 50000,
    "commission_amount": 7000,
    "commission_percent": 14,
    "payment_url": "https://pay.kaspi.kz/pay/abc123",
    "qr_code": "https://qr.kaspi.kz/abc123",
    "terminal_id": 1
  }
}
```

**Важно!** 
- Для Kaspi Gold и Red: `amount` = `original_amount` (комиссия 0%)
- Для Kaspi Кредит и Рассрочка: `amount` = `original_amount` + 14%

### Логика работы

1. Система ищет свободный Kaspi терминал
2. Блокирует терминал на время операции
3. Отправляет запрос на терминал для инициации платежа
4. Терминал генерирует QR код  
5. Система считывает QR с камеры
6. Возвращает `payment_url` для клиента
7. Клиент переходит по ссылке и оплачивает
8. Система автоматически проверяет статус и отправляет webhook

---

## Проверка статуса

### GET /api/payment/{transaction_id}/status

Проверить текущий статус платежа.

**Публичный эндпоинт**

**Пример запроса:**
```http
GET https://byfly-pay.com/api/payment/A3F5D9E2B1C8/status
```

**Ответ (200 OK):**
```json
{
  "success": true,
  "data": {
    "transaction_id": "A3F5D9E2B1C8",
    "status": "paid",
    "amount": 57000,
    "paid_amount": 57000,
    "remaining_amount": 0
  }
}
```

### Автоматическая проверка

Система автоматически проверяет статус на терминале при каждом запросе `/status` если:
- Статус = `processing`
- Есть `kaspi_terminal_id` и `terminal_operation_id`

**Процесс:**
1. Запрос к терминалу с `processId` (terminal_operation_id)
2. Получение статуса от терминала
3. Если `success` → обновление на `paid` или `partially_paid`
4. Если `failed` → обновление на `failed`
5. Автоматическая отправка webhook

---

## Webhook уведомления

### Настройка

При создании платежа укажите:
```json
{
  "webhook_url": "https://your-system.com/webhooks/payment",
  "webhook_secret": "your_secret_key"
}
```

### Когда отправляются

- ✅ **`paid`** - платеж полностью оплачен
- 🔄 **`partially_paid`** - частичная оплата (требуется доплата)
- ❌ **`failed`** - ошибка оплаты
- 🚫 **`cancelled`** - платеж отменен
- 💸 **`refunded`** - возврат средств

### Формат webhook

**Заголовки:**
```http
Content-Type: application/json
User-Agent: ByFly-Payment-Center/1.0
X-Webhook-Event: paid
X-Webhook-Timestamp: 1736775000
X-Webhook-Signature: sha256_hmac_signature
```

**Тело (Payload):**
```json
{
  "event": "paid",
  "transaction_id": "A3F5D9E2B1C8",
  "amount": 57000,
  "original_amount": 50000,
  "paid_amount": 57000,
  "actual_amount_received": 57000,
  "remaining_amount": 0,
  "currency": "KZT",
  "status": "paid",
  "description": "Оплата тура в Дубай #12345",
  "created_at": "2026-01-13 14:30:00",
  "paid_at": "2026-01-13 14:35:00",
  "metadata": {
    "order_id": "12345",
    "tour_name": "Дубай 7 дней",
    "customer_id": "CU-456"
  },
  "timestamp": 1736775000
}
```

### Проверка подписи (HMAC)

```javascript
// Node.js пример
const crypto = require('crypto');

function verifyWebhook(payload, signature, secret) {
  const hmac = crypto
    .createHmac('sha256', secret)
    .update(JSON.stringify(payload))
    .digest('hex');
    
  return hmac === signature;
}

// Использование
const isValid = verifyWebhook(
  req.body,
  req.headers['x-webhook-signature'],
  'your_secret_key'
);
```

```php
// PHP пример
function verifyWebhook($payload, $signature, $secret) {
    $hmac = hash_hmac('sha256', json_encode($payload), $secret);
    return hash_equals($hmac, $signature);
}

// Использование
$payload = json_decode(file_get_contents('php://input'), true);
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$isValid = verifyWebhook($payload, $signature, 'your_secret_key');
```

### Повторные попытки

- Первая попытка: сразу после события
- Вторая попытка: при ручном retry (через админку)
- Третья попытка: при ручном retry

**Успешный webhook:** HTTP 200-299

**Логирование:**
Все попытки webhook сохраняются в таблице `webhook_logs` с:
- Request payload
- Response code  
- Response body
- Error message (если есть)
- Timestamp

---

## Сценарии использования

### 1. Полная оплата Kaspi Gold

```javascript
// 1. Создать платеж
POST /api/payment/init
{
  "amount": 50000,
  "webhook_url": "https://your-site.com/webhook"
}

// Ответ
{
  "transaction_id": "ABC123",
  "amount": 50000,
  "status": "pending"
}

// 2. Инициировать Kaspi оплату
POST /api/payment/ABC123/kaspi
{
  "payment_method": "kaspi_gold"
}

// Ответ
{
  "payment_url": "https://pay.kaspi.kz/pay/xyz",
  "amount": 50000,
  "commission_amount": 0
}

// 3. Отправить ссылку клиенту
// Клиент переходит по payment_url и оплачивает

// 4. Проверить статус (или дождаться webhook)
GET /api/payment/ABC123/status

// Ответ
{
  "status": "paid",
  "paid_amount": 50000,
  "remaining_amount": 0
}

// 5. Получить webhook
{
  "event": "paid",
  "transaction_id": "ABC123",
  "paid_amount": 50000,
  "remaining_amount": 0
}
```

### 2. Оплата в кредит Kaspi

```javascript
// 1. Создать платеж
POST /api/payment/init
{
  "amount": 100000,
  "description": "Тур в Турцию"
}

// 2. Инициировать Kaspi Кредит
POST /api/payment/ABC123/kaspi
{
  "payment_method": "kaspi_kredit"
}

// Ответ - комиссия 14% добавлена!
{
  "payment_url": "https://pay.kaspi.kz/pay/xyz",
  "original_amount": 100000,
  "commission_amount": 14000,
  "commission_percent": 14,
  "amount": 114000  // <- Итого к оплате
}

// 3. Клиент оплачивает 114000₸ в кредит

// 4. Webhook
{
  "event": "paid",
  "original_amount": 100000,
  "amount": 114000,
  "paid_amount": 114000,
  "remaining_amount": 0
}
```

### 3. Частичная оплата (клиент выбрал не тот способ)

```javascript
// 1. Создать платеж на 100000₸
POST /api/payment/init
{
  "amount": 100000
}

// 2. Клиент выбрал Gold, но система ожидает 100000₸
POST /api/payment/ABC123/kaspi
{
  "payment_method": "kaspi_gold"
}

// Ответ
{
  "amount": 100000,
  "commission_amount": 0
}

// 3. Клиент по ошибке оплатил в кредит
// Терминал получил только 87719₸ (100000 - 14% = 87719)

// 4. Webhook - частичная оплата!
{
  "event": "partially_paid",
  "amount": 100000,
  "paid_amount": 87719,
  "actual_amount_received": 87719,
  "remaining_amount": 12281  // <- Требуется доплата
}

// 5. Генерируем новую ссылку на доплату 12281₸
POST /api/payment/ABC123/kaspi
{
  "payment_method": "kaspi_gold"
}

// Ответ
{
  "amount": 12281,
  "original_amount": 12281
}

// 6. Клиент доплачивает 12281₸

// 7. Webhook - полная оплата!
{
  "event": "paid",
  "amount": 100000,
  "paid_amount": 100000,
  "remaining_amount": 0
}
```

### 4. Клиент выбрал кредит, но оплатил Gold

```javascript
// 1. Создать платеж
POST /api/payment/init
{
  "amount": 100000
}

// 2. Клиент выбрал Kaspi Кредит
POST /api/payment/ABC123/kaspi
{
  "payment_method": "kaspi_kredit"
}

// Ответ - с комиссией!
{
  "amount": 114000,  // 100000 + 14%
  "commission_amount": 14000
}

// 3. Клиент оплатил через Gold (без кредита)
// Терминал получил только 100000₸ вместо 114000₸

// 4. Webhook - частичная оплата!
{
  "event": "partially_paid",
  "amount": 114000,
  "paid_amount": 100000,
  "remaining_amount": 14000  // <- Не хватает комиссии
}

// 5. Два варианта:
// A) Попросить доплатить 14000₸
// B) Пересоздать платеж без комиссии (если клиент не хочет кредит)
```

### 5. Проверка статуса в цикле

```javascript
// Polling каждую секунду
async function waitForPayment(transactionId, maxAttempts = 60) {
  for (let i = 0; i < maxAttempts; i++) {
    const response = await fetch(
      `https://byfly-pay.com/api/payment/${transactionId}/status`
    );
    const data = await response.json();
    
    if (data.data.status === 'paid') {
      console.log('✅ Оплачено!');
      return data.data;
    }
    
    if (data.data.status === 'partially_paid') {
      console.log('🔄 Частичная оплата, требуется доплата');
      return data.data;
    }
    
    if (data.data.status === 'failed' || data.data.status === 'cancelled') {
      console.log('❌ Платеж не прошел');
      return data.data;
    }
    
    // Ждем 1 секунду
    await new Promise(resolve => setTimeout(resolve, 1000));
  }
  
  console.log('⏰ Timeout');
  return null;
}

// Использование
const result = await waitForPayment('ABC123');
if (result && result.status === 'paid') {
  // Платеж успешен
}
```

---

## Комиссии и расчеты

### Логика расчета комиссий

#### Kaspi Gold / Red (0% комиссия)
```
Сумма заказа: 50000₸
Комиссия: 0₸
Итого к оплате: 50000₸
Получаем: 50000₸
```

#### Kaspi Кредит (14% комиссия)
```
Сумма заказа: 50000₸
Комиссия: 50000 * 0.14 = 7000₸
Итого к оплате: 57000₸
Получаем: 50000₸ (клиент платит комиссию)
```

#### Kaspi Рассрочка (14% комиссия)
```
Сумма заказа: 50000₸
Комиссия: 50000 * 0.14 = 7000₸
Итого к оплате: 57000₸
Получаем: 50000₸ (клиент платит комиссию)
```

### Допуск на округление

При проверке полной оплаты используется допуск:
```
tolerance = max(500₸, amount * 0.005)
```

**Примеры:**
- Сумма 10000₸ → допуск 500₸
- Сумма 100000₸ → допуск 500₸ (0.5% = 500₸)
- Сумма 200000₸ → допуск 1000₸ (0.5% = 1000₸)

Если `remaining_amount <= tolerance`, платеж считается полностью оплаченным.

### Важные моменты

1. **Комиссия всегда добавляется к сумме** для кредита/рассрочки
2. **Комиссию платит клиент**, не продавец
3. **Actual amount received** - реальная сумма полученная от терминала
4. **Partial payment** - если клиент оплатил не тем способом

---

## Обработка ошибок

### HTTP коды

| Код | Значение |
|-----|----------|
| 200 | OK - успешный запрос |
| 201 | Created - платеж создан |
| 400 | Bad Request - неверные данные |
| 401 | Unauthorized - неверный API токен |
| 404 | Not Found - транзакция не найдена |
| 500 | Internal Server Error - ошибка сервера |

### Формат ошибки

```json
{
  "success": false,
  "message": "Сумма должна быть больше 0",
  "error_code": 400
}
```

### Типичные ошибки

#### Неверный API Token
```json
{
  "success": false,
  "message": "Invalid API token",
  "error_code": 401
}
```

#### Транзакция не найдена
```json
{
  "success": false,
  "message": "Транзакция не найдена",
  "error_code": 404
}
```

#### Все терминалы заняты
```json
{
  "success": false,
  "message": "Все терминалы заняты, попробуйте позже",
  "error_code": 400
}
```

#### Терминал недоступен
```json
{
  "success": false,
  "message": "Терминал недоступен. Проверьте подключение к сети терминалов",
  "error_code": 400
}
```

---

## Примеры кода

### PHP (cURL)

```php
<?php

class ByFlyPaymentAPI {
    private $apiUrl = 'https://byfly-pay.com/api';
    private $apiToken = 'YOUR_64_CHAR_API_TOKEN';
    
    /**
     * Создать платеж
     */
    public function createPayment($data) {
        $response = $this->request('POST', '/payment/init', $data);
        return $response;
    }
    
    /**
     * Получить информацию о платеже
     */
    public function getPayment($transactionId) {
        $response = $this->request('GET', "/payment/{$transactionId}");
        return $response;
    }
    
    /**
     * Инициировать Kaspi оплату
     */
    public function initiateKaspi($transactionId, $paymentMethod = 'kaspi_gold') {
        $response = $this->request('POST', "/payment/{$transactionId}/kaspi", [
            'payment_method' => $paymentMethod
        ]);
        return $response;
    }
    
    /**
     * Проверить статус
     */
    public function checkStatus($transactionId) {
        $response = $this->request('GET', "/payment/{$transactionId}/status");
        return $response;
    }
    
    /**
     * Отменить платеж
     */
    public function cancelPayment($transactionId) {
        $response = $this->request('POST', "/payment/{$transactionId}/cancel");
        return $response;
    }
    
    /**
     * Проверить webhook подпись
     */
    public function verifyWebhook($payload, $signature, $secret) {
        $hmac = hash_hmac('sha256', json_encode($payload), $secret);
        return hash_equals($hmac, $signature);
    }
    
    /**
     * Выполнить API запрос
     */
    private function request($method, $endpoint, $data = null) {
        $url = $this->apiUrl . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Token: ' . $this->apiToken
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        $result['http_code'] = $httpCode;
        
        return $result;
    }
}

// Использование
$api = new ByFlyPaymentAPI();

// Создать платеж
$payment = $api->createPayment([
    'amount' => 50000,
    'description' => 'Тур в Дубай',
    'webhook_url' => 'https://your-site.com/webhook',
    'webhook_secret' => 'your_secret',
    'metadata' => [
        'order_id' => '12345'
    ]
]);

if ($payment['success']) {
    $transactionId = $payment['data']['transaction_id'];
    
    // Инициировать Kaspi
    $kaspi = $api->initiateKaspi($transactionId, 'kaspi_gold');
    
    if ($kaspi['success']) {
        // Отправить ссылку клиенту
        $paymentUrl = $kaspi['data']['payment_url'];
        echo "Ссылка для оплаты: {$paymentUrl}";
    }
}
```

### Node.js (Axios)

```javascript
const axios = require('axios');
const crypto = require('crypto');

class ByFlyPaymentAPI {
  constructor() {
    this.apiUrl = 'https://byfly-pay.com/api';
    this.apiToken = 'YOUR_64_CHAR_API_TOKEN';
  }
  
  /**
   * Создать платеж
   */
  async createPayment(data) {
    return this.request('POST', '/payment/init', data);
  }
  
  /**
   * Получить информацию о платеже
   */
  async getPayment(transactionId) {
    return this.request('GET', `/payment/${transactionId}`);
  }
  
  /**
   * Инициировать Kaspi оплату
   */
  async initiateKaspi(transactionId, paymentMethod = 'kaspi_gold') {
    return this.request('POST', `/payment/${transactionId}/kaspi`, {
      payment_method: paymentMethod
    });
  }
  
  /**
   * Проверить статус
   */
  async checkStatus(transactionId) {
    return this.request('GET', `/payment/${transactionId}/status`);
  }
  
  /**
   * Проверить webhook подпись
   */
  verifyWebhook(payload, signature, secret) {
    const hmac = crypto
      .createHmac('sha256', secret)
      .update(JSON.stringify(payload))
      .digest('hex');
      
    return hmac === signature;
  }
  
  /**
   * Выполнить API запрос
   */
  async request(method, endpoint, data = null) {
    try {
      const response = await axios({
        method,
        url: this.apiUrl + endpoint,
        headers: {
          'Content-Type': 'application/json',
          'X-API-Token': this.apiToken
        },
        data
      });
      
      return response.data;
    } catch (error) {
      if (error.response) {
        return error.response.data;
      }
      throw error;
    }
  }
}

// Использование
const api = new ByFlyPaymentAPI();

async function processPayment() {
  // Создать платеж
  const payment = await api.createPayment({
    amount: 50000,
    description: 'Тур в Дубай',
    webhook_url: 'https://your-site.com/webhook',
    webhook_secret: 'your_secret',
    metadata: {
      order_id: '12345'
    }
  });
  
  if (payment.success) {
    const transactionId = payment.data.transaction_id;
    
    // Инициировать Kaspi
    const kaspi = await api.initiateKaspi(transactionId, 'kaspi_gold');
    
    if (kaspi.success) {
      console.log('Ссылка для оплаты:', kaspi.data.payment_url);
      
      // Polling статуса
      const status = await waitForPayment(transactionId);
      console.log('Итоговый статус:', status);
    }
  }
}

// Ожидание оплаты
async function waitForPayment(transactionId, maxAttempts = 60) {
  for (let i = 0; i < maxAttempts; i++) {
    const result = await api.checkStatus(transactionId);
    
    if (result.data.status === 'paid') {
      return result.data;
    }
    
    if (['partially_paid', 'failed', 'cancelled'].includes(result.data.status)) {
      return result.data;
    }
    
    await new Promise(resolve => setTimeout(resolve, 1000));
  }
  
  return null;
}

// Обработка webhook
app.post('/webhook', (req, res) => {
  const payload = req.body;
  const signature = req.headers['x-webhook-signature'];
  const secret = 'your_secret';
  
  if (api.verifyWebhook(payload, signature, secret)) {
    console.log('✅ Webhook валиден');
    
    if (payload.event === 'paid') {
      // Платеж полностью оплачен
      console.log(`Оплачено: ${payload.transaction_id}`);
    } else if (payload.event === 'partially_paid') {
      // Частичная оплата
      console.log(`Требуется доплата: ${payload.remaining_amount}₸`);
    }
    
    res.status(200).send('OK');
  } else {
    console.log('❌ Неверная подпись');
    res.status(400).send('Invalid signature');
  }
});
```

---

## Чек-лист интеграции

### Подготовка
- [ ] Получен API токен от администратора
- [ ] Настроен webhook endpoint на вашем сервере
- [ ] Генерирован webhook_secret для HMAC подписи
- [ ] Протестирован webhook endpoint (принимает POST, возвращает 200)

### Создание платежа
- [ ] Запрос на `/payment/init` с корректными данными
- [ ] Сохранен `transaction_id` в вашей базе
- [ ] Проверка что `amount` корректный

### Kaspi оплата
- [ ] Запрос на `/payment/{id}/kaspi` с нужным `payment_method`
- [ ] Получена `payment_url`
- [ ] Ссылка отправлена клиенту (SMS/Email/QR)

### Проверка статуса
- [ ] Периодическая проверка `/payment/{id}/status`
- [ ] Обработка всех статусов: `paid`, `partially_paid`, `failed`
- [ ] Логика доплаты для `partially_paid`

### Webhook
- [ ] Проверка HMAC подписи
- [ ] Обработка события `paid`
- [ ] Обработка события `partially_paid`
- [ ] Логирование всех webhook событий
- [ ] Возврат HTTP 200 для успешных webhook

### Тестирование
- [ ] Тест полной оплаты Kaspi Gold
- [ ] Тест оплаты в кредит с комиссией
- [ ] Тест частичной оплаты (неправильный способ)
- [ ] Тест доплаты после partial payment
- [ ] Тест webhook уведомлений

---

## Поддержка

**Email:** support@byfly.travel  
**Телефон:** +7 778 002 16 66  
**Документация:** https://byfly-pay.com/docs  
**API Status:** https://byfly-pay.com/status  

---

**© 2026 ByFly Travel - Все права защищены**
