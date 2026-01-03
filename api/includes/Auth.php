<?php
/**
 * Authentication & Authorization Class
 */

require_once __DIR__ . '/JWT.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Отправка SMS кода через smsc.kz
     */
    public function sendSmsCode($phone) {
        // Очистка номера телефона
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Проверка существования админа
        $admin = $this->db->fetch("SELECT * FROM admins WHERE phone = ? AND is_active = 1", [$phone]);
        if (!$admin) {
            throw new Exception("Пользователь не найден");
        }

        // Генерация кода
        $code = str_pad(rand(0, 999999), SMS_CODE_LENGTH, '0', STR_PAD_LEFT);
        
        // Время истечения
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . SMS_CODE_EXPIRE_MINUTES . ' minutes'));

        // Сохранение кода в БД
        $this->db->insert('sms_codes', [
            'phone' => $phone,
            'code' => $code,
            'expires_at' => $expiresAt
        ]);

        // Отправка SMS через smsc.kz
        $message = "Ваш код для входа в ByFly Payment Center: " . $code;
        $this->sendSms($phone, $message);

        return true;
    }

    /**
     * Проверка SMS кода и выдача JWT токена
     */
    public function verifyCode($phone, $code) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Поиск кода
        $smsCode = $this->db->fetch(
            "SELECT * FROM sms_codes 
             WHERE phone = ? AND code = ? AND is_used = 0 AND expires_at > NOW() 
             ORDER BY created_at DESC LIMIT 1",
            [$phone, $code]
        );

        if (!$smsCode) {
            // Увеличиваем счетчик попыток
            $this->db->query(
                "UPDATE sms_codes SET attempts = attempts + 1 
                 WHERE phone = ? AND code = ? AND is_used = 0",
                [$phone, $code]
            );
            throw new Exception("Неверный или истекший код");
        }

        // Помечаем код как использованный
        $this->db->update('sms_codes', ['is_used' => 1], 'id = ?', [$smsCode['id']]);

        // Получаем админа
        $admin = $this->db->fetch("SELECT * FROM admins WHERE phone = ? AND is_active = 1", [$phone]);
        if (!$admin) {
            throw new Exception("Пользователь не найден");
        }

        // Обновляем время последнего входа
        $this->db->update('admins', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$admin['id']]);

        // Генерация JWT токена
        $currentTime = time();
        $expirationTime = $currentTime + (JWT_EXPIRE_HOURS * 3600);
        
        $payload = [
            'admin_id' => $admin['id'],
            'phone' => $admin['phone'],
            'is_super_admin' => (bool)$admin['is_super_admin'],
            'exp' => $expirationTime
        ];

        $token = JWT::encode($payload, JWT_SECRET);

        // Сохранение токена в БД
        try {
            $tokenId = $this->db->insert('admin_tokens', [
                'admin_id' => $admin['id'],
                'token' => $token,
                'expires_at' => date('Y-m-d H:i:s', $payload['exp'])
            ]);
        } catch (Exception $e) {
            throw new Exception("Ошибка сохранения токена");
        }

        return [
            'token' => $token,
            'expires_at' => $payload['exp'],
            'admin' => [
                'id' => $admin['id'],
                'phone' => $admin['phone'],
                'name' => $admin['name'],
                'country_code' => $admin['country_code'],
                'country_name' => $admin['country_name'],
                'is_super_admin' => (bool)$admin['is_super_admin'],
                'allowed_countries' => json_decode($admin['allowed_countries'], true),
                'allowed_payment_systems' => json_decode($admin['allowed_payment_systems'], true)
            ]
        ];
    }

    /**
     * Проверка JWT токена
     */
    public function validateToken($token) {
        try {
            $payload = JWT::decode($token, JWT_SECRET);

            // Проверка существования токена в БД
            $dbToken = $this->db->fetch(
                "SELECT id, admin_id, expires_at, created_at FROM admin_tokens WHERE token = ? AND expires_at > NOW()",
                [$token]
            );

            if (!$dbToken) {
                return false;
            }

            // Получение админа
            $admin = $this->db->fetch(
                "SELECT * FROM admins WHERE id = ? AND is_active = 1",
                [$payload->admin_id]
            );

            if (!$admin) {
                return false;
            }

            return [
                'admin_id' => $admin['id'],
                'phone' => $admin['phone'],
                'is_super_admin' => (bool)$admin['is_super_admin'],
                'allowed_countries' => json_decode($admin['allowed_countries'], true),
                'allowed_payment_systems' => json_decode($admin['allowed_payment_systems'], true)
            ];
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Проверка API токена источника
     */
    public function validateApiToken($token) {
        $source = $this->db->fetch(
            "SELECT * FROM sources WHERE api_token = ? AND is_active = 1",
            [$token]
        );

        if (!$source) {
            return false;
        }

        // Проверка IP если настроены ограничения
        if ($source['allowed_ips']) {
            $allowedIps = json_decode($source['allowed_ips'], true);
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            
            if (!empty($allowedIps) && !in_array($clientIp, $allowedIps)) {
                return false;
            }
        }

        return [
            'source_id' => $source['id'],
            'name' => $source['name'],
            'webhook_url' => $source['webhook_url']
        ];
    }

    /**
     * Отправка SMS через smsc.kz
     */
    private function sendSms($phone, $message) {
        $url = "https://smsc.kz/sys/send.php";
        
        $data = [
            'login' => SMS_LOGIN,
            'psw' => SMS_PASSWORD,
            'phones' => $phone,
            'mes' => $message,
            'sender' => SMS_SENDER,
            'charset' => 'utf-8',
            'fmt' => 3 // JSON response
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
            error_log("SMS send failed: " . $response);
            throw new Exception("Ошибка отправки SMS");
        }

        $result = json_decode($response, true);
        if (isset($result['error'])) {
            error_log("SMS error: " . $result['error']);
            throw new Exception("Ошибка отправки SMS: " . $result['error']);
        }

        return true;
    }

    /**
     * Отправка SMS подписанту для согласования платежа
     */
    public function sendApprovalSms($phone, $paymentId, $paymentTitle, $amount, $currency, $approvalToken) {
        // Генерация уникальной ссылки для подписания
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        $approvalUrl = $baseUrl . "/approve-payment?token=" . $approvalToken;
        
        // Формируем сообщение
        $message = "ByFly Pay: Новый платеж на согласование\n";
        $message .= $paymentTitle . "\n";
        $message .= "Сумма: " . number_format($amount, 0, '.', ' ') . " " . $currency . "\n";
        $message .= "Подписать: " . $approvalUrl;
        
        $this->sendSms($phone, $message);
        
        return true;
    }

    /**
     * Генерация случайного токена
     */
    public static function generateToken($length = API_TOKEN_LENGTH) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Генерация уникального токена для подписи платежа
     */
    public static function generateApprovalToken() {
        return bin2hex(random_bytes(32)); // 64 символа
    }
}
