# API Gateway - Полная документация

## 🎯 Все реализованные API endpoints

### Базовый URL: `https://byfly-pay.com/api`

Все запросы требуют заголовок: `X-API-Token: ВАШ_ТОКЕН`

---

## 1️⃣ Платежи

### POST /api/payment/init
Создание нового платежа

**Request:**
```json
{
  "amount": 5000,
  "currency": "KZT",
  "description": "Оплата заказа #123",
  "webhook_url": "https://your-site.com/webhook",
  "webhook_secret": "your_secret",
  "metadata": {
    "user_id": "12345",
    "order_id": "ORD-001"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "transaction_id": "9D6973B7...",
    "payment_url": "https://byfly-pay.com/pay/9D6973B7...",
    "amount": 5000,
    "currency": "KZT",
    "status": "pending",
    "expires_at": "2025-12-27 16:00:00"
  }
}
```

### GET /api/payment/{id}
Информация о платеже

### GET /api/payment/{id}/status
Только статус платежа

---

## 2️⃣ Транзакции

### GET /api/transactions
История транзакций с фильтрами

**Query параметры:**
- `status` - фильтр по статусу (paid, pending, etc.)
- `date_from` - дата от (YYYY-MM-DD)
- `date_to` - дата до (YYYY-MM-DD)
- `amount_from` - сумма от
- `amount_to` - сумма до
- `limit` - количество записей (по умолчанию 100, макс 1000)
- `offset` - смещение для пагинации

**Пример:**
```
GET /api/transactions?status=paid&date_from=2025-12-01&limit=50
```

**Response:**
```json
{
  "success": true,
  "data": {
    "transactions": [...],
    "total": 150,
    "limit": 50,
    "offset": 0
  }
}
```

### GET /api/transaction/{id}
Детали конкретной транзакции

**Response:**
```json
{
  "success": true,
  "data": {
    "transaction": {
      "transaction_id": "9D6973B7...",
      "amount": 5000,
      "paid_amount": 5050,
      "actual_amount_received": 5000,
      "metadata": {
        "user_id": "12345",
        "order_id": "ORD-001"
      }
    },
    "partial_payments": [...],
    "webhook_logs": [...]
  }
}
```

---

## 3️⃣ Справочники

### GET /api/countries
Список доступных стран для оплаты

**Response:**
```json
{
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
}
```

### GET /api/payment-methods
Список способов оплаты

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "kaspi_gold",
      "name": "Kaspi Gold",
      "provider": "Kaspi",
      "commission_percent": 1.0,
      "has_credit": false,
      "has_installment": false
    },
    {
      "id": 3,
      "code": "kaspi_credit",
      "name": "Kaspi Кредит",
      "provider": "Kaspi",
      "commission_percent": 0,
      "has_credit": true,
      "credit_commission_percent": 14.0
    }
  ]
}
```

### GET /api/exchange-rates
Актуальные курсы валют

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "from_currency": "KZT",
      "to_currency": "USD",
      "rate": 0.0021,
      "updated_at": "2025-12-26 12:00:00"
    }
  ]
}
```

### GET /api/terminals
Статусы терминалов Kaspi

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Terminal 1",
      "status": "online",
      "is_busy": false,
      "is_active": true,
      "last_check": "2025-12-26 15:00:00",
      "last_used": "2025-12-26 14:30:00"
    }
  ]
}
```

---

## 4️⃣ Статистика

### GET /api/statistics
Статистика оплат за период

**Query параметры:**
- `date_from` - дата от (по умолчанию -30 дней)
- `date_to` - дата до (по умолчанию сегодня)

**Пример:**
```
GET /api/statistics?date_from=2025-12-01&date_to=2025-12-26
```

**Response:**
```json
{
  "success": true,
  "data": {
    "period": {
      "from": "2025-12-01",
      "to": "2025-12-26"
    },
    "summary": {
      "total_transactions": 150,
      "paid_count": 120,
      "partially_paid_count": 5,
      "cancelled_count": 25,
      "total_paid_amount": 750500,
      "total_received_amount": 745000,
      "total_commission": 5500,
      "refund_count": 3,
      "refunded_amount": 15000
    },
    "by_payment_method": [
      {
        "payment_method": "Kaspi Gold",
        "count": 80,
        "total_amount": 400000,
        "total_commission": 4000
      },
      {
        "payment_method": "Kaspi Кредит",
        "count": 30,
        "total_amount": 300000,
        "total_commission": 42000
      }
    ],
    "credit_installment": {
      "count": 40,
      "total_amount": 400000,
      "total_commission": 56000
    }
  }
}
```

---

## 5️⃣ Вебхуки

### События:
- `paid` - полная оплата
- `partially_paid` - частичная оплата
- `cancelled` - отмена
- `refunded` - возврат
- `expired` - истечение

### Формат вебхука:
```json
POST https://your-site.com/webhook
Headers:
  X-Webhook-Event: paid
  X-Webhook-Signature: abc123...

Body:
{
  "event": "paid",
  "transaction_id": "9D6973B7...",
  "amount": 5000,
  "paid_amount": 5050,
  "actual_amount_received": 5000,
  "metadata": {
    "user_id": "12345",
    "order_id": "ORD-001"
  },
  "timestamp": 1703598000
}
```

### Проверка подписи:
```php
$payload = file_get_contents('php://input');
$signature = hash_hmac('sha256', $payload, 'your_secret');

if ($signature === $_SERVER['HTTP_X_WEBHOOK_SIGNATURE']) {
    // Подпись валидна
}
```

---

## 📊 Полный список API:

| Метод | Endpoint | Описание |
|-------|----------|----------|
| POST | /api/payment/init | Создать платеж |
| GET | /api/payment/{id} | Информация о платеже |
| GET | /api/payment/{id}/status | Статус платежа |
| GET | /api/transactions | История транзакций (с фильтрами) |
| GET | /api/transaction/{id} | Детали транзакции |
| GET | /api/countries | Список стран |
| GET | /api/payment-methods | Способы оплаты |
| GET | /api/exchange-rates | Курсы валют |
| GET | /api/terminals | Статусы терминалов |
| GET | /api/statistics | Статистика за период |

---

## 🚀 Готово к использованию!

Все endpoints протестированы и готовы к работе!

**Интерактивная документация:** https://byfly-pay.com/api-docs




