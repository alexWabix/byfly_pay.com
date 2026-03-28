<?php
/**
 * Простая система авторизации через SMS код
 * Готовая к использованию в любом проекте
 */

class SMSAuth {
    private $db;
    private $smsLogin = 'Byfly2024';
    private $smsPassword = '2350298aweA!@';
    
    public function __construct($db) {
        $this->db = $db; // PDO подключение
    }
    
    /**
     * ШАГ 1: Отправить SMS код на телефон
     * 
     * @param string $phone Номер телефона (+77780021666)
     * @return array Результат отправки
     */
    public function sendCode($phone) {
        // Очистка номера телефона
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Проверка формата
        if (!preg_match('/^\+7\d{10}$/', $phone)) {
            return [
                'success' => false,
                'error' => 'Неверный формат номера. Используйте +77XXXXXXXXX'
            ];
        }
        
        // Генерация 6-значного кода
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Срок действия: 10 минут
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Удаляем старые коды для этого номера
        $stmt = $this->db->prepare("
            DELETE FROM sms_codes 
            WHERE phone = ? AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        $stmt->execute([$phone]);
        
        // Сохраняем новый код в БД
        $stmt = $this->db->prepare("
            INSERT INTO sms_codes (phone, code, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$phone, $code, $expiresAt]);
        
        // Отправляем SMS
        $smsResult = $this->sendSMS($phone, "Ваш код для входа: {$code}");
        
        if (!$smsResult['success']) {
            return [
                'success' => false,
                'error' => 'Ошибка отправки SMS: ' . $smsResult['error']
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Код отправлен на номер ' . $phone,
            'expires_in' => 600 // секунд
        ];
    }
    
    /**
     * ШАГ 2: Проверить код и авторизовать пользователя
     * 
     * @param string $phone Номер телефона
     * @param string $code Код из SMS
     * @return array Результат с токеном авторизации
     */
    public function verifyCode($phone, $code) {
        // Очистка
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        $code = preg_replace('/[^0-9]/', '', $code);
        
        // Ищем код в БД
        $stmt = $this->db->prepare("
            SELECT * FROM sms_codes 
            WHERE phone = ? 
            AND code = ? 
            AND expires_at > NOW()
            AND used_at IS NULL
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$phone, $code]);
        $smsCode = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$smsCode) {
            return [
                'success' => false,
                'error' => 'Неверный или истекший код'
            ];
        }
        
        // Отмечаем код как использованный
        $stmt = $this->db->prepare("
            UPDATE sms_codes 
            SET used_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$smsCode['id']]);
        
        // Ищем или создаем пользователя
        $user = $this->findOrCreateUser($phone);
        
        // Создаем сессию / токен
        $token = $this->createSession($user['id']);
        
        return [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'phone' => $user['phone'],
                'name' => $user['name']
            ]
        ];
    }
    
    /**
     * Найти или создать пользователя
     */
    private function findOrCreateUser($phone) {
        // Ищем пользователя
        $stmt = $this->db->prepare("
            SELECT * FROM users 
            WHERE phone = ?
        ");
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Если не найден - создаем
        if (!$user) {
            $stmt = $this->db->prepare("
                INSERT INTO users (phone, created_at) 
                VALUES (?, NOW())
            ");
            $stmt->execute([$phone]);
            
            $userId = $this->db->lastInsertId();
            
            $user = [
                'id' => $userId,
                'phone' => $phone,
                'name' => null
            ];
        }
        
        return $user;
    }
    
    /**
     * Создать сессию (токен авторизации)
     */
    private function createSession($userId) {
        // Генерируем случайный токен
        $token = bin2hex(random_bytes(32));
        
        // Срок действия: 30 дней
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Сохраняем в БД
        $stmt = $this->db->prepare("
            INSERT INTO sessions (user_id, token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $token, $expiresAt]);
        
        return $token;
    }
    
    /**
     * Проверить токен авторизации
     * 
     * @param string $token Токен из заголовка/cookie
     * @return array|false Данные пользователя или false
     */
    public function validateToken($token) {
        $stmt = $this->db->prepare("
            SELECT u.* 
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.token = ? 
            AND s.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        return $user;
    }
    
    /**
     * Выход (удаление сессии)
     */
    public function logout($token) {
        $stmt = $this->db->prepare("
            DELETE FROM sessions 
            WHERE token = ?
        ");
        $stmt->execute([$token]);
        
        return ['success' => true];
    }
    
    /**
     * Отправка SMS через SMSC.KZ
     */
    private function sendSMS($phone, $message) {
        $url = "https://smsc.kz/sys/send.php";
        
        $data = [
            'login' => $this->smsLogin,
            'psw' => $this->smsPassword,
            'phones' => $phone,
            'mes' => $message,
            'charset' => 'utf-8',
            'fmt' => 3
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
                'error' => $result['error']
            ];
        }

        return [
            'success' => true,
            'id' => $result['id']
        ];
    }
}

// ========================================
// ИСПОЛЬЗОВАНИЕ
// ========================================

// Подключение к БД
$db = new PDO('mysql:host=localhost;dbname=myapp', 'user', 'password');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Инициализация
$auth = new SMSAuth($db);

// ========================================
// ПРИМЕР 1: API endpoint для отправки кода
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] === 'send-code') {
    $data = json_decode(file_get_contents('php://input'), true);
    $phone = $data['phone'] ?? '';
    
    $result = $auth->sendCode($phone);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// ========================================
// ПРИМЕР 2: API endpoint для проверки кода
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] === 'verify-code') {
    $data = json_decode(file_get_contents('php://input'), true);
    $phone = $data['phone'] ?? '';
    $code = $data['code'] ?? '';
    
    $result = $auth->verifyCode($phone, $code);
    
    if ($result['success']) {
        // Устанавливаем cookie с токеном
        setcookie('auth_token', $result['token'], time() + 2592000, '/'); // 30 дней
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// ========================================
// ПРИМЕР 3: Проверка авторизации
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['action'] === 'me') {
    $token = $_COOKIE['auth_token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    
    $user = $auth->validateToken($token);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['user' => $user]);
    exit;
}

// ========================================
// ПРИМЕР 4: Выход
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] === 'logout') {
    $token = $_COOKIE['auth_token'] ?? '';
    
    $auth->logout($token);
    
    setcookie('auth_token', '', time() - 3600, '/');
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}
