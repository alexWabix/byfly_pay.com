<?php
/**
 * Kaspi Terminal Integration Class
 */

class KaspiTerminal {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Получить свободный терминал
     */
    public function getFreeTerminal() {
        $terminal = $this->db->fetch(
            "SELECT * FROM kaspi_terminals 
             WHERE is_active = 1 AND is_busy = 0 
             ORDER BY last_used ASC, id ASC 
             LIMIT 1"
        );

        if (!$terminal) {
            throw new Exception("Все терминалы заняты, попробуйте позже");
        }

        return $terminal;
    }

    /**
     * Заблокировать терминал
     */
    public function lockTerminal($terminalId) {
        return $this->db->update(
            'kaspi_terminals',
            [
                'is_busy' => 1,
                'last_used' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$terminalId]
        );
    }

    /**
     * Разблокировать терминал
     */
    public function unlockTerminal($terminalId) {
        return $this->db->update(
            'kaspi_terminals',
            ['is_busy' => 0],
            'id = ?',
            [$terminalId]
        );
    }

    /**
     * Инициировать платеж на терминале
     */
    public function initiatePayment($terminalId, $amount) {
        $terminal = $this->db->fetch("SELECT * FROM kaspi_terminals WHERE id = ?", [$terminalId]);
        
        if (!$terminal) {
            throw new Exception("Терминал не найден");
        }

        // Пробуем оба IP адреса
        $ipAddresses = [
            $terminal['ip_address'],
            'http://109.175.215.40',
            'http://188.127.40.70'
        ];
        
        $response = null;
        $httpCode = 0;
        $lastError = '';
        
        foreach (array_unique($ipAddresses) as $ip) {
            $terminalUrl = rtrim($ip, '/') . ':' . $terminal['port'];
            $paymentUrl = $terminalUrl . '/v2/payment?amount=' . (int)$amount;

            error_log("KASPI: Trying terminal at $paymentUrl");

            $ch = curl_init($paymentUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            error_log("KASPI: Response from $ip - HTTP $httpCode, Error: $curlError");

            if ($httpCode === 200 && $response) {
                error_log("KASPI: Successfully connected to $ip");
                break;
            }
            
            $lastError = $curlError ?: "HTTP $httpCode";
        }

        if ($httpCode !== 200 || !$response) {
            error_log("KASPI: All IPs failed. Last error: $lastError");
            throw new Exception("Терминал недоступен. Проверьте подключение к сети терминалов. Последняя ошибка: $lastError");
        }

        $data = json_decode($response, true);
        error_log("KASPI: Terminal response - " . json_encode($data));

        // Проверяем формат ответа как в Flutter
        if (!isset($data['statusCode']) || $data['statusCode'] != 0 || !isset($data['data'])) {
            $errorMsg = $data['message'] ?? 'Неизвестная ошибка';
            throw new Exception("Ошибка терминала: $errorMsg");
        }

        $processId = $data['data']['processId'] ?? null;
        if (!$processId) {
            throw new Exception("Терминал не вернул processId");
        }

        error_log("KASPI: Payment initiated, processId = $processId");

        // Ждем 2 секунды перед считыванием QR (как в Flutter)
        sleep(2);

        // Считывание QR кода с камеры
        $qrData = $this->readQrCode($terminal);

        return [
            'terminal_id' => $terminalId,
            'process_id' => $processId,
            'qr_data' => $qrData,
            'payment_url' => $this->generatePaymentUrl($qrData)
        ];
    }

    /**
     * Считывание QR кода с камеры
     * 
     * @param array $terminal Данные терминала
     * @param bool $isRefund Флаг возврата (для возврата QR уже на экране, используем меньший таймаут)
     */
    private function readQrCode($terminal, $isRefund = false) {
        $cameraId = $terminal['camera_id'];
        
        // Используем один endpoint для всех операций: /scan-qr/
        // Но для возврата используем меньший таймаут, так как QR уже на экране
        if ($isRefund) {
            // Для возврата: QR уже на экране, достаточно 5 секунд
            $cameraUrl = "http://109.175.215.40:3000/scan-qr/{$cameraId}?timeout=5000";
            error_log("KASPI REFUND: Scanning QR from screen, camera $cameraId, URL: $cameraUrl");
        } else {
            // Для оплаты: ждем появления QR, таймаут 10 секунд
            $cameraUrl = "http://109.175.215.40:3000/scan-qr/{$cameraId}?timeout=10000";
            error_log("KASPI: Scanning QR from camera $cameraId, URL: $cameraUrl");
        }

        $ch = curl_init($cameraUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $isRefund ? 10 : 15); // Меньший CURL таймаут для возврата
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("KASPI: Camera response - HTTP $httpCode, Error: $curlError, Body: $response");

        if ($httpCode !== 200 || !$response) {
            $errorDetails = $curlError ? " ($curlError)" : "";
            throw new Exception("Ошибка сканирования QR кода (код: $httpCode){$errorDetails}");
        }

        $data = json_decode($response, true);
        
        // Формат ответа: {success: true, qrData: '...'}
        if (!isset($data['success']) || !$data['success'] || !isset($data['qrData'])) {
            $errorMsg = $data['message'] ?? 'QR код не обнаружен';
            throw new Exception("Не удалось считать QR код: $errorMsg");
        }

        error_log("KASPI: QR code read successfully: " . $data['qrData']);
        
        return $data['qrData'];
    }

    /**
     * Проверка статуса платежа на терминале
     */
    public function checkPaymentStatus($terminalId, $processId) {
        $terminal = $this->db->fetch("SELECT * FROM kaspi_terminals WHERE id = ?", [$terminalId]);
        
        if (!$terminal) {
            throw new Exception("Терминал не найден");
        }

        $terminalUrl = rtrim($terminal['ip_address'], '/') . ':' . $terminal['port'];
        
        // GET запрос как в Flutter: /v2/status?processId=XXX
        $ch = curl_init($terminalUrl . '/v2/status?processId=' . $processId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return ['status' => 'unknown'];
        }

        $data = json_decode($response, true);
        
        if (!isset($data['statusCode']) || $data['statusCode'] != 0) {
            return ['status' => 'unknown'];
        }

        $status = $data['data']['status'] ?? 'unknown';
        
        return [
            'status' => $status, // success, fail, unknown
            'amount' => $data['data']['amount'] ?? 0,
            'transaction_id' => $data['data']['transactionId'] ?? null,
            'payment_method' => $data['data']['chequeInfo']['method'] ?? null,
            'details' => $data['data']
        ];
    }

    /**
     * Актуализация статуса платежа (когда status = unknown)
     */
    public function actualizePaymentStatus($terminalId, $processId) {
        $terminal = $this->db->fetch("SELECT * FROM kaspi_terminals WHERE id = ?", [$terminalId]);
        
        if (!$terminal) {
            throw new Exception("Терминал не найден");
        }

        $terminalUrl = rtrim($terminal['ip_address'], '/') . ':' . $terminal['port'];
        
        // GET запрос: /v2/actualize?processId=XXX
        $ch = curl_init($terminalUrl . '/v2/actualize?processId=' . $processId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return ['status' => 'unknown'];
        }

        $data = json_decode($response, true);
        
        if (!isset($data['statusCode']) || $data['statusCode'] != 0) {
            return ['status' => 'unknown'];
        }

        $status = $data['data']['status'] ?? 'unknown';
        
        return [
            'status' => $status, // success, fail, unknown
            'amount' => $data['data']['amount'] ?? 0,
            'transaction_id' => $data['data']['transactionId'] ?? null,
            'payment_method' => $data['data']['chequeInfo']['method'] ?? null,
            'details' => $data['data']
        ];
    }

    /**
     * Отмена платежа на терминале
     */
    public function cancelPayment($terminalId, $processId) {
        $terminal = $this->db->fetch("SELECT * FROM kaspi_terminals WHERE id = ?", [$terminalId]);
        
        if (!$terminal) {
            throw new Exception("Терминал не найден");
        }

        $terminalUrl = rtrim($terminal['ip_address'], '/') . ':' . $terminal['port'];
        
        // GET запрос как в Flutter: /v2/cancel?processId=XXX
        $ch = curl_init($terminalUrl . '/v2/cancel?processId=' . $processId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return false;
        }

        $data = json_decode($response, true);
        return isset($data['statusCode']) && $data['statusCode'] == 0;
    }

    /**
     * Возврат платежа (refund) на терминале
     * Используется для отмены уже проведенных платежей
     * 
     * @param int $terminalId ID терминала
     * @param float $amount Сумма возврата
     * @param string $method Метод оплаты (qr, card, alaqan)
     * @param string $transactionId ID оригинальной транзакции от терминала
     */
    public function refundPayment($terminalId, $amount, $method, $transactionId) {
        $terminal = $this->db->fetch("SELECT * FROM kaspi_terminals WHERE id = ?", [$terminalId]);
        
        if (!$terminal) {
            throw new Exception("Терминал не найден");
        }

        // Валидация параметров согласно документации Kaspi
        if (empty($method)) {
            throw new Exception("Параметр method обязателен (qr, card, alaqan)");
        }
        
        if (!in_array($method, ['qr', 'card', 'alaqan'])) {
            throw new Exception("Некорректный method. Допустимые значения: qr, card, alaqan");
        }
        
        if (empty($transactionId)) {
            throw new Exception("Параметр transactionId обязателен");
        }

        error_log("KASPI REFUND: Starting refund on terminal $terminalId for amount $amount, method: $method, transactionId: $transactionId");

        // Блокируем терминал
        $this->lockTerminal($terminalId);

        try {
            // Пробуем оба IP адреса
            $ipAddresses = [
                $terminal['ip_address'],
                'http://109.175.215.40',
                'http://188.127.40.70'
            ];
            
            $response = null;
            $httpCode = 0;
            $lastError = '';
            
            foreach (array_unique($ipAddresses) as $ip) {
                $terminalUrl = rtrim($ip, '/') . ':' . $terminal['port'];
                
                // Формируем URL согласно документации Kaspi Smart POS
                // GET /v2/refund?method={method}&amount={amount}&transactionId={transactionId}
                $refundUrl = $terminalUrl . '/v2/refund?' . http_build_query([
                    'method' => $method,
                    'amount' => (int)$amount,
                    'transactionId' => $transactionId
                ]);
                
                error_log("KASPI REFUND: Trying $refundUrl");

                // Добавляем заголовок accesstoken согласно документации
                $headers = ['Content-Type: application/json'];
                if ($terminal['access_token']) {
                    $headers[] = 'accesstoken: ' . $terminal['access_token'];
                }

                $ch = curl_init($refundUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                error_log("KASPI REFUND: Response from $ip - HTTP $httpCode");

                if ($httpCode === 200 && $response) {
                    error_log("KASPI REFUND: Successfully connected to $ip");
                    break;
                }
                
                $lastError = $curlError ?: "HTTP $httpCode";
            }

            if ($httpCode !== 200 || !$response) {
                throw new Exception("Терминал недоступен для возврата. Последняя ошибка: $lastError");
            }

            $data = json_decode($response, true);
            error_log("KASPI REFUND: Terminal response - " . json_encode($data));

            // Проверяем формат ответа
            if (!isset($data['statusCode']) || $data['statusCode'] != 0 || !isset($data['data'])) {
                $errorMsg = $data['message'] ?? 'Неизвестная ошибка';
                throw new Exception("Ошибка возврата на терминале: $errorMsg");
            }

            $processId = $data['data']['processId'] ?? null;
            if (!$processId) {
                throw new Exception("Терминал не вернул processId для возврата");
            }

            error_log("KASPI REFUND: Refund initiated, processId = $processId");

            // Ждем 5 секунд чтобы QR гарантированно появился на экране терминала
            // Терминал может показывать экран подтверждения перед QR
            sleep(5);

            // Считывание QR кода подтверждения возврата с камеры
            // Передаем isRefund = true для использования меньшего таймаута (QR уже на экране)
            $qrData = $this->readQrCode($terminal, true);

            error_log("KASPI REFUND: QR code read successfully: " . $qrData);

            // ВАЖНО! Для возврата клиент должен подтвердить операцию через QR
            // Поэтому НЕ ждем автоматического подтверждения, а сразу возвращаем QR ссылку
            // Администратор передаст эту ссылку клиенту для подтверждения
            
            error_log("KASPI REFUND: QR link obtained, client needs to confirm: " . $qrData);
            
            // Освобождаем терминал
            $this->unlockTerminal($terminalId);

            // Генерируем ссылку для клиента (замена qr.kaspi.kz на pay.kaspi.kz/pay)
            $paymentUrl = $this->generatePaymentUrl($qrData);

            // Возвращаем данные с QR кодом и ссылкой
            return [
                'terminal_id' => $terminalId,
                'process_id' => $processId,
                'qr_code' => $qrData,  // Оригинальный QR для сканирования (qr.kaspi.kz)
                'payment_url' => $paymentUrl, // Ссылка для клика (pay.kaspi.kz/pay)
                'refund_amount' => $amount,
                'status' => 'waiting_confirmation',
                'message' => 'QR код получен. Передайте ссылку клиенту для подтверждения возврата.',
                'requires_client_confirmation' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+3 minutes'))
            ];

        } catch (Exception $e) {
            // Освобождаем терминал в случае ошибки
            $this->unlockTerminal($terminalId);
            throw $e;
        }
    }

    /**
     * Отправка GET запроса на терминал для проверки статуса
     */
    private function sendTerminalRequest($url, $data = []) {
        // Если есть параметры - добавляем в URL
        if (!empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Terminal request error: $error");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("Terminal returned HTTP $httpCode: $response");
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Генерация ссылки для оплаты из QR данных
     */
    private function generatePaymentUrl($qrData) {
        // Преобразуем qr.kaspi.kz в pay.kaspi.kz/pay (как в Flutter)
        if (strpos($qrData, 'qr.kaspi.kz') !== false) {
            return str_replace('qr.kaspi.kz', 'pay.kaspi.kz/pay', $qrData);
        }
        
        // QR код уже содержит готовую ссылку
        return $qrData;
    }

    /**
     * Проверка статуса терминала (согласно документации Kaspi Smart POS)
     */
    public function checkTerminalStatus($terminalId) {
        $terminal = $this->db->fetch("SELECT * FROM kaspi_terminals WHERE id = ?", [$terminalId]);
        
        if (!$terminal) {
            return ['status' => 'not_found', 'message' => 'Терминал не найден'];
        }

        // Блокируем терминал на время проверки
        $this->lockTerminal($terminalId);

        try {
            // Шаг 1: Проверяем доступность камеры
            $cameraId = $terminal['camera_id'];
            $cameraUrl = "http://109.175.215.40:3000/capture/{$cameraId}";
            
            error_log("CHECK STATUS: Testing camera $cameraId at $cameraUrl");
            
            $ch = curl_init($cameraUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            curl_exec($ch);
            $cameraStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($cameraStatus !== 200) {
                throw new Exception("Камера недоступна (HTTP $cameraStatus)");
            }
            
            error_log("CHECK STATUS: Camera OK");

            // Шаг 2: Проверяем deviceinfo терминала (согласно документации)
            // URL: https://IP:8080/v2/deviceinfo
            // Header: accesstoken
            $terminalUrl = rtrim($terminal['ip_address'], '/') . ':' . $terminal['port'];
            
            error_log("CHECK STATUS: Testing terminal at $terminalUrl/v2/deviceinfo");
            
            $headers = ['Content-Type: application/json'];
            
            // Добавляем accessToken если есть
            if ($terminal['access_token']) {
                $headers[] = 'accesstoken: ' . $terminal['access_token'];
            }
            
            $ch = curl_init($terminalUrl . '/v2/deviceinfo');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                throw new Exception("Терминал недоступен (HTTP $httpCode, Error: $curlError)");
            }
            
            $deviceInfo = json_decode($response, true);
            
            // Проверяем формат ответа согласно документации
            if (!isset($deviceInfo['statusCode']) || $deviceInfo['statusCode'] != 0) {
                $error = $deviceInfo['errorText'] ?? 'Неизвестная ошибка';
                throw new Exception("Терминал вернул ошибку: $error");
            }
            
            error_log("CHECK STATUS: Terminal OK - " . json_encode($deviceInfo['data']));

            // Шаг 3: Проверяем зависшие операции (> 4 минут)
            $lastUsed = strtotime($terminal['last_used'] ?? '1970-01-01');
            $minutesSinceLastUse = (time() - $lastUsed) / 60;
            
            if ($terminal['is_busy'] && $minutesSinceLastUse > 4) {
                error_log("CHECK STATUS: Terminal stuck (last use: {$minutesSinceLastUse} min ago), resetting...");
                
                // Отменяем зависшую операцию (без processId - общий cancel)
                try {
                    $ch = curl_init($terminalUrl . '/v2/cancel');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_exec($ch);
                    curl_close($ch);
                    
                    error_log("CHECK STATUS: Cancelled stuck payment");
                } catch (Exception $e) {
                    error_log("CHECK STATUS: Failed to cancel - " . $e->getMessage());
                }
            }

            // Обновляем статус на "online" и освобождаем
            $this->db->update(
                'kaspi_terminals',
                [
                    'status' => 'online',
                    'last_check' => date('Y-m-d H:i:s'),
                    'is_busy' => 0
                ],
                'id = ?',
                [$terminalId]
            );

            return [
                'status' => 'online',
                'message' => 'Терминал доступен',
                'device_info' => $deviceInfo['data'] ?? null,
                'camera_ok' => true,
                'stuck_cleared' => ($terminal['is_busy'] && $minutesSinceLastUse > 4)
            ];

        } catch (Exception $e) {
            error_log("CHECK STATUS: Error - " . $e->getMessage());
            
            // Обновляем статус на "offline" и освобождаем
            $this->db->update(
                'kaspi_terminals',
                [
                    'status' => 'offline',
                    'last_check' => date('Y-m-d H:i:s'),
                    'is_busy' => 0
                ],
                'id = ?',
                [$terminalId]
            );

            return [
                'status' => 'offline',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Получить все терминалы
     */
    public function getAllTerminals() {
        return $this->db->fetchAll("SELECT * FROM kaspi_terminals ORDER BY id ASC");
    }

    /**
     * Добавить терминал
     */
    public function addTerminal($data) {
        return $this->db->insert('kaspi_terminals', [
            'name' => $data['name'] ?? null,
            'ip_address' => $data['ip_address'],
            'port' => $data['port'] ?? 8080,
            'access_token' => $data['access_token'] ?? null,
            'camera_id' => $data['camera_id'] ?? null,
            'camera_url' => $data['camera_url'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ]);
    }

    /**
     * Обновить терминал
     */
    public function updateTerminal($id, $data) {
        $updateData = [];
        
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['ip_address'])) $updateData['ip_address'] = $data['ip_address'];
        if (isset($data['port'])) $updateData['port'] = $data['port'];
        if (isset($data['access_token'])) $updateData['access_token'] = $data['access_token'];
        if (isset($data['camera_id'])) $updateData['camera_id'] = $data['camera_id'];
        if (isset($data['camera_url'])) $updateData['camera_url'] = $data['camera_url'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];

        return $this->db->update('kaspi_terminals', $updateData, 'id = ?', [$id]);
    }

    /**
     * Удалить терминал
     */
    public function deleteTerminal($id) {
        return $this->db->delete('kaspi_terminals', 'id = ?', [$id]);
    }
}
