# Примеры отправки SMS через PHP

## 📱 Вариант 1: SMSC.KZ (используется в проекте)

### Простая отправка SMS

```php
<?php
/**
 * Отправка SMS через SMSC.KZ
 */

function sendSMS($phone, $message) {
    $url = "https://smsc.kz/sys/send.php";
    
    $data = [
        'login' => 'Byfly2024',           // Ваш логин
        'psw' => '2350298aweA!@',         // Ваш пароль
        'phones' => $phone,                // Номер телефона
        'mes' => $message,                 // Текст сообщения
        'sender' => 'ByFly',              // Имя отправителя (опционально)
        'charset' => 'utf-8',
        'fmt' => 3                         // JSON ответ
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "HTTP {$httpCode}"
        ];
    }

    $result = json_decode($response, true);
    
    if (isset($result['error_code'])) {
        return [
            'success' => false,
            'error' => $result['error'],
            'error_code' => $result['error_code']
        ];
    }

    return [
        'success' => true,
        'id' => $result['id'],
        'cnt' => $result['cnt']
    ];
}

// Использование
$result = sendSMS('+77780021666', 'Привет! Ваш код: 123456');

if ($result['success']) {
    echo "✅ SMS отправлено! ID: {$result['id']}\n";
} else {
    echo "❌ Ошибка: {$result['error']}\n";
}
```

---

## 📱 Вариант 2: SMS класс (рекомендуется)

### Создание класса для работы с SMS

```php
<?php
/**
 * SMS Service Class для SMSC.KZ
 */

class SMSService {
    private $login = 'Byfly2024';
    private $password = '2350298aweA!@';
    private $sender = 'ByFly';
    private $apiUrl = 'https://smsc.kz/sys/send.php';
    
    /**
     * Отправить SMS
     * 
     * @param string $phone Номер телефона (+77XXXXXXXXX)
     * @param string $message Текст сообщения
     * @return array Результат отправки
     */
    public function send($phone, $message) {
        // Логирование
        error_log("SMS: Sending to {$phone}, message: {$message}");
        
        $data = [
            'login' => $this->login,
            'psw' => $this->password,
            'phones' => $phone,
            'mes' => $message,
            'sender' => $this->sender,
            'charset' => 'utf-8',
            'fmt' => 3
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("SMS: CURL Error - {$error}");
            return [
                'success' => false,
                'error' => $error
            ];
        }

        if ($httpCode !== 200) {
            error_log("SMS: HTTP Error - {$httpCode}");
            return [
                'success' => false,
                'error' => "HTTP {$httpCode}"
            ];
        }

        $result = json_decode($response, true);
        
        if (isset($result['error_code'])) {
            error_log("SMS: API Error - {$result['error']}");
            return [
                'success' => false,
                'error' => $result['error'],
                'error_code' => $result['error_code']
            ];
        }

        error_log("SMS: Sent successfully, ID: {$result['id']}");
        
        return [
            'success' => true,
            'id' => $result['id'],
            'cnt' => $result['cnt']
        ];
    }
    
    /**
     * Отправить SMS с кодом подтверждения
     */
    public function sendVerificationCode($phone, $code) {
        $message = "Ваш код подтверждения: {$code}. Никому не сообщайте этот код.";
        return $this->send($phone, $message);
    }
    
    /**
     * Отправить уведомление об оплате
     */
    public function sendPaymentNotification($phone, $amount, $currency = 'KZT') {
        $message = "Оплата получена: {$amount} {$currency}. Спасибо!";
        return $this->send($phone, $message);
    }
    
    /**
     * Отправить ссылку для подтверждения
     */
    public function sendApprovalLink($phone, $paymentId, $amount, $url) {
        $message = "Согласуйте платеж #{$paymentId} на сумму {$amount} KZT: {$url}";
        return $this->send($phone, $message);
    }
    
    /**
     * Проверить баланс
     */
    public function getBalance() {
        $url = "https://smsc.kz/sys/balance.php";
        
        $data = [
            'login' => $this->login,
            'psw' => $this->password,
            'fmt' => 3
        ];
        
        $ch = curl_init($url . '?' . http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return [
            'balance' => $result['balance'] ?? 0,
            'currency' => $result['cur'] ?? 'KZT'
        ];
    }
}

// Использование
$sms = new SMSService();

// Простая отправка
$result = $sms->send('+77780021666', 'Тестовое сообщение');

// Код подтверждения
$result = $sms->sendVerificationCode('+77780021666', '123456');

// Уведомление об оплате
$result = $sms->sendPaymentNotification('+77780021666', 50000);

// Ссылка для подтверждения
$result = $sms->sendApprovalLink(
    '+77780021666', 
    'PAY-12345', 
    50000, 
    'https://byfly-pay.com/approve/token123'
);

// Проверить баланс
$balance = $sms->getBalance();
echo "Баланс: {$balance['balance']} {$balance['currency']}\n";
```

---

## 📱 Вариант 3: Альтернативные провайдers

### 3.1 Twilio

```php
<?php
require 'vendor/autoload.php';
use Twilio\Rest\Client;

$accountSid = 'YOUR_ACCOUNT_SID';
$authToken = 'YOUR_AUTH_TOKEN';
$twilioNumber = '+1234567890';

$client = new Client($accountSid, $authToken);

$message = $client->messages->create(
    '+77780021666', // Кому
    [
        'from' => $twilioNumber,
        'body' => 'Привет из Twilio!'
    ]
);

echo "SID: " . $message->sid;
```

### 3.2 Vonage (Nexmo)

```php
<?php
require 'vendor/autoload.php';

$apiKey = 'YOUR_API_KEY';
$apiSecret = 'YOUR_API_SECRET';

$client = new \Vonage\Client(
    new \Vonage\Client\Credentials\Basic($apiKey, $apiSecret)
);

$response = $client->sms()->send(
    new \Vonage\SMS\Message\SMS(
        '+77780021666',
        'ByFly',
        'Привет из Vonage!'
    )
);

$message = $response->current();
echo $message->getStatus();
```

### 3.3 Мобильные операторы Казахстана

#### Beeline KZ
```php
<?php
function sendSmsBeeline($phone, $message) {
    $url = "https://sms.beeline.kz/api/send";
    
    $data = [
        'user' => 'YOUR_USER',
        'pass' => 'YOUR_PASS',
        'phone' => $phone,
        'text' => $message
    ];
    
    // Аналогично SMSC
}
```

#### Tele2 KZ
```php
<?php
function sendSmsTele2($phone, $message) {
    $url = "https://api.tele2.kz/sms/send";
    
    $data = [
        'token' => 'YOUR_TOKEN',
        'to' => $phone,
        'message' => $message
    ];
    
    // API запрос
}
```

---

## 📱 Вариант 4: Как используется в проекте ByFly

### Из класса Auth.php

```php
<?php
/**
 * Отправка SMS кода авторизации
 */
public function sendCode($phone) {
    // Очистка телефона
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Генерация кода
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Сохранение в БД
    $this->db->insert('sms_codes', [
        'phone' => $phone,
        'code' => $code,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes'))
    ]);
    
    // Текст SMS
    $message = "Ваш код для входа в ByFly Payment Center: {$code}";
    
    // Отправка
    return $this->sendSms($phone, $message);
}

/**
 * Отправка SMS со ссылкой для подтверждения платежа
 */
public function sendApprovalSms($phone, $paymentId, $title, $amount, $currency, $token) {
    $url = "https://byfly-pay.com/approve-payment/{$token}";
    
    $message = "Подтвердите платеж:\n";
    $message .= "ID: {$paymentId}\n";
    $message .= "{$title}\n";
    $message .= "Сумма: {$amount} {$currency}\n";
    $message .= "Ссылка: {$url}";
    
    return $this->sendSms($phone, $message);
}

/**
 * Базовая отправка SMS
 */
private function sendSms($phone, $message) {
    $url = "https://smsc.kz/sys/send.php";
    
    $data = [
        'login' => SMS_LOGIN,      // из config.php
        'psw' => SMS_PASSWORD,     // из config.php
        'phones' => $phone,
        'mes' => $message,
        'sender' => SMS_SENDER,    // из config.php
        'charset' => 'utf-8',
        'fmt' => 3
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Failed to send SMS: HTTP {$httpCode}");
    }

    $result = json_decode($response, true);
    
    if (isset($result['error_code'])) {
        throw new Exception("SMS Error: {$result['error']}");
    }

    return true;
}
```

---

## 📱 Полный пример использования

```php
<?php
require_once 'SMSService.php';

// Инициализация
$sms = new SMSService();

// 1. Отправка кода подтверждения
echo "1. Отправка кода подтверждения...\n";
$code = rand(100000, 999999);
$result = $sms->sendVerificationCode('+77780021666', $code);

if ($result['success']) {
    echo "✅ Код отправлен! SMS ID: {$result['id']}\n";
} else {
    echo "❌ Ошибка: {$result['error']}\n";
}

// 2. Уведомление об оплате
echo "\n2. Уведомление об оплате...\n";
$result = $sms->sendPaymentNotification('+77780021666', 50000, 'KZT');

if ($result['success']) {
    echo "✅ Уведомление отправлено!\n";
}

// 3. Ссылка для подтверждения
echo "\n3. Ссылка для подтверждения...\n";
$result = $sms->sendApprovalLink(
    '+77780021666',
    'PAY-12345',
    50000,
    'https://byfly-pay.com/approve/abc123'
);

if ($result['success']) {
    echo "✅ Ссылка отправлена!\n";
}

// 4. Проверка баланса
echo "\n4. Проверка баланса...\n";
$balance = $sms->getBalance();
echo "💰 Баланс: {$balance['balance']} {$balance['currency']}\n";

// 5. Массовая рассылка
echo "\n5. Массовая рассылка...\n";
$phones = ['+77780021666', '+77001234567', '+77051234567'];

foreach ($phones as $phone) {
    $result = $sms->send($phone, 'Важное уведомление для всех клиентов!');
    
    if ($result['success']) {
        echo "✅ Отправлено на {$phone}\n";
    } else {
        echo "❌ Ошибка для {$phone}: {$result['error']}\n";
    }
    
    // Задержка между отправками
    sleep(1);
}
```

---

## 🔧 Коды ошибок SMSC.KZ

| Код | Описание |
|-----|----------|
| 1 | Ошибка в параметрах |
| 2 | Неверный логин или пароль |
| 3 | Недостаточно средств |
| 4 | IP адрес временно заблокирован |
| 5 | Неверный формат даты |
| 6 | Сообщение запрещено |
| 7 | Неверный формат номера |
| 8 | Сообщение на этот номер не может быть доставлено |
| 9 | Отправка более одного сообщения в секунду |

## 💡 Советы

1. **Используйте переменные окружения** для хранения логина/пароля
2. **Логируйте все SMS** в базу данных
3. **Добавьте rate limiting** (не более 1 SMS в минуту на номер)
4. **Проверяйте баланс** перед отправкой
5. **Обрабатывайте ошибки** правильно
6. **Используйте шаблоны** для сообщений
7. **Тестируйте на своем номере** перед массовой рассылкой

## 📊 Логирование SMS в БД

```sql
CREATE TABLE sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    sms_id VARCHAR(50),
    status VARCHAR(20) DEFAULT 'sent',
    error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

```php
<?php
function logSMS($phone, $message, $result) {
    global $db;
    
    $db->insert('sms_logs', [
        'phone' => $phone,
        'message' => $message,
        'sms_id' => $result['id'] ?? null,
        'status' => $result['success'] ? 'sent' : 'failed',
        'error' => $result['error'] ?? null
    ]);
}
```

---

**© 2026 ByFly Travel - Примеры отправки SMS**
