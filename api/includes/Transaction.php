<?php
/**
 * Transaction Management Class
 */

class Transaction {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Создание новой транзакции
     */
    public function create($sourceId, $data) {
        // Генерация уникального ID транзакции
        $transactionId = $this->generateTransactionId();

        // Вычисление суммы с комиссией если указан способ оплаты
        $amount = $data['amount'];
        $originalAmount = $amount;
        $commissionAmount = 0;

        if (isset($data['payment_method_id'])) {
            $paymentMethod = $this->getPaymentMethod($data['payment_method_id']);
            
            // Используем единый метод расчета комиссии
            $calculation = $this->calculateCommission($paymentMethod, $amount, $data['payment_type'] ?? null);
            $commissionAmount = $calculation['commission'];
            $amount = $calculation['total'];
        }

        // Время истечения (по умолчанию 1 час)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Создание транзакции
        $id = $this->db->insert('transactions', [
            'transaction_id' => $transactionId,
            'source_id' => $sourceId,
            'amount' => $amount,
            'original_amount' => $originalAmount,
            'currency' => $data['currency'] ?? 'KZT',
            'description' => $data['description'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'remaining_amount' => $amount,
            'commission_amount' => $commissionAmount,
            'webhook_url' => $data['webhook_url'] ?? null,
            'webhook_secret' => $data['webhook_secret'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'expires_at' => $expiresAt,
            'status' => 'pending'
        ]);

        // Лог создания
        $this->addLog($id, 'created', 'pending', 'Транзакция создана', $data);

        return $this->getById($id);
    }

    /**
     * Обработка платежа через Kaspi
     */
    public function processKaspiPayment($transactionId, $paymentMethodCode = 'kaspi_gold') {
        $transaction = $this->getByTransactionId($transactionId);
        
        if (!$transaction) {
            throw new Exception("Транзакция не найдена");
        }

        // Разрешаем повторную оплату для pending, partially_paid, failed, cancelled
        // ВАЖНО! Также разрешаем если needs_additional_payment = 1 (требуется доплата)
        $allowedStatuses = ['pending', 'partially_paid', 'failed', 'cancelled'];
        $needsPayment = !empty($transaction['needs_additional_payment']);
        
        if (!in_array($transaction['status'], $allowedStatuses) && !$needsPayment) {
            throw new Exception("Транзакция уже обработана");
        }
        
        // Если требуется доплата - сбрасываем на pending для обработки
        if ($needsPayment && $transaction['status'] === 'paid') {
            $this->db->update(
                'transactions',
                ['status' => 'partially_paid'],
                'id = ?',
                [$transaction['id']]
            );
            $transaction['status'] = 'partially_paid';
        }
        
        // Если транзакция была failed/cancelled, сбрасываем на pending
        if ($transaction['status'] === 'failed' || $transaction['status'] === 'cancelled') {
            $this->db->update(
                'transactions',
                ['status' => 'pending'],
                'id = ?',
                [$transaction['id']]
            );
            $transaction['status'] = 'pending';
        }

        // Получаем способ оплаты
        $paymentMethod = $this->db->fetch(
            "SELECT * FROM payment_methods WHERE code = ? AND is_active = 1",
            [$paymentMethodCode]
        );

        if (!$paymentMethod) {
            throw new Exception("Способ оплаты не найден");
        }

        // Вычисляем сумму с комиссией используя единый метод
        $remainingAmount = $transaction['remaining_amount'];
        
        // Определяем тип оплаты на основе характеристик способа оплаты
        $paymentType = null;
        if ($paymentMethod['has_credit']) {
            $paymentType = 'credit';
        } elseif ($paymentMethod['has_installment']) {
            $paymentType = 'installment';
        }
        
        // Используем единый метод расчета комиссии
        $calculation = $this->calculateCommission($paymentMethod, $remainingAmount, $paymentType);
        $commissionAmount = $calculation['commission'];
        $totalAmount = $calculation['total'];
        $commissionPercent = $calculation['percent'];

        // Получаем свободный терминал
        $kaspiTerminal = new KaspiTerminal();
        $terminal = $kaspiTerminal->getFreeTerminal();

        try {
            // Блокируем терминал
            $kaspiTerminal->lockTerminal($terminal['id']);

            // Обновляем статус транзакции
            $this->db->update(
                'transactions',
                [
                    'status' => 'processing',
                    'kaspi_terminal_id' => $terminal['id'],
                    'payment_method_id' => $paymentMethod['id']
                ],
                'id = ?',
                [$transaction['id']]
            );

            // Инициируем платеж на терминале
            $paymentData = $kaspiTerminal->initiatePayment($terminal['id'], $totalAmount);

            // Обновляем транзакцию с processId от терминала
            $this->db->update(
                'transactions',
                [
                    'payment_url' => $paymentData['payment_url'],
                    'qr_code' => $paymentData['qr_data'],
                    'terminal_operation_id' => $paymentData['process_id'] ?? null
                ],
                'id = ?',
                [$transaction['id']]
            );
            
            // Обновляем терминал с ID операции
            if (!empty($paymentData['process_id'])) {
                $this->db->update(
                    'kaspi_terminals',
                    ['last_operation_id' => $paymentData['process_id']],
                    'id = ?',
                    [$terminal['id']]
                );
            }

            // Лог
            $this->addLog(
                $transaction['id'],
                'kaspi_initiated',
                'processing',
                'Платеж инициирован на терминале',
                $paymentData
            );

            return [
                'transaction_id' => $transactionId,
                'amount' => $totalAmount,
                'original_amount' => $remainingAmount,
                'commission_amount' => $commissionAmount,
                'commission_percent' => $commissionPercent,
                'payment_url' => $paymentData['payment_url'],
                'qr_code' => $paymentData['qr_data'],
                'terminal_id' => $terminal['id']
            ];

        } catch (Exception $e) {
            // Освобождаем терминал в случае ошибки
            $kaspiTerminal->unlockTerminal($terminal['id']);
            
            $this->db->update(
                'transactions',
                ['status' => 'failed'],
                'id = ?',
                [$transaction['id']]
            );

            $this->addLog(
                $transaction['id'],
                'kaspi_error',
                'failed',
                $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Проверка статуса платежа
     */
    public function checkStatus($transactionId) {
        $transaction = $this->getByTransactionId($transactionId);
        
        if (!$transaction) {
            throw new Exception("Транзакция не найдена");
        }

        // Если статус не processing, возвращаем текущий статус
        if ($transaction['status'] !== 'processing') {
            return [
                'transaction_id' => $transactionId,
                'status' => $transaction['status'],
                'amount' => $transaction['amount'],
                'paid_amount' => $transaction['paid_amount'],
                'remaining_amount' => $transaction['remaining_amount']
            ];
        }

        // Проверяем статус на терминале
        if ($transaction['kaspi_terminal_id'] && $transaction['terminal_operation_id']) {
            $kaspiTerminal = new KaspiTerminal();
            
            try {
                // ВАЖНО! Используем terminal_operation_id (processId от терминала), а не transaction_id
                $status = $kaspiTerminal->checkPaymentStatus(
                    $transaction['kaspi_terminal_id'],
                    $transaction['terminal_operation_id']
                );

                $this->addLog(
                    $transaction['id'],
                    'status_check',
                    $status['status'],
                    'Проверка статуса платежа',
                    $status
                );

                // Если оплачено
                if ($status['status'] === 'paid' || $status['status'] === 'success') {
                    $this->completePayment($transaction['id'], $status);
                }

                // Если ошибка
                if ($status['status'] === 'failed' || $status['status'] === 'error') {
                    $this->failPayment($transaction['id'], $status);
                }

            } catch (Exception $e) {
                error_log("Status check error: " . $e->getMessage());
            }
        }

        // Возвращаем обновленную транзакцию
        $transaction = $this->getById($transaction['id']);

        return [
            'transaction_id' => $transactionId,
            'status' => $transaction['status'],
            'amount' => $transaction['amount'],
            'paid_amount' => $transaction['paid_amount'],
            'remaining_amount' => $transaction['remaining_amount']
        ];
    }

    /**
     * Завершение платежа
     */
    private function completePayment($id, $statusData) {
        $transaction = $this->getById($id);
        
        $paidAmount = $statusData['amount'] ?? $transaction['remaining_amount'];
        $totalPaid = $transaction['paid_amount'] + $paidAmount;

        $status = 'paid';
        $remainingAmount = $transaction['amount'] - $totalPaid;

        // Допуск на округление: 500₸ или 0.5% от суммы (что больше)
        $tolerance = max(500, $transaction['amount'] * 0.005); // 500₸ или 0.5%
        
        if ($remainingAmount > $tolerance) {
            $status = 'partially_paid';
        } else {
            // Если разница в пределах допуска - считаем полностью оплаченным
            $remainingAmount = 0;
        }

        $this->db->update(
            'transactions',
            [
                'status' => $status,
                'paid_amount' => $totalPaid,
                'remaining_amount' => max(0, $remainingAmount),
                'paid_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$id]
        );

        // Освобождаем терминал
        if ($transaction['kaspi_terminal_id']) {
            $kaspiTerminal = new KaspiTerminal();
            $kaspiTerminal->unlockTerminal($transaction['kaspi_terminal_id']);
        }

        // Отправляем webhook
        $this->sendWebhook($id);

        $this->addLog($id, 'completed', $status, 'Платеж выполнен', $statusData);
    }

    /**
     * Пометить платеж как неудачный
     */
    private function failPayment($id, $statusData) {
        $transaction = $this->getById($id);

        $this->db->update(
            'transactions',
            ['status' => 'failed'],
            'id = ?',
            [$id]
        );

        // Освобождаем терминал
        if ($transaction['kaspi_terminal_id']) {
            $kaspiTerminal = new KaspiTerminal();
            $kaspiTerminal->unlockTerminal($transaction['kaspi_terminal_id']);
        }

        $this->addLog($id, 'failed', 'failed', 'Платеж не выполнен', $statusData);
    }

    /**
     * Отмена транзакции
     */
    public function cancel($transactionId) {
        $transaction = $this->getByTransactionId($transactionId);
        
        if (!$transaction) {
            throw new Exception("Транзакция не найдена");
        }

        if ($transaction['status'] === 'paid') {
            throw new Exception("Нельзя отменить оплаченную транзакцию");
        }

        // Отменяем платеж на терминале если есть
        if ($transaction['kaspi_terminal_id']) {
            $kaspiTerminal = new KaspiTerminal();
            try {
                $kaspiTerminal->cancelPayment(
                    $transaction['kaspi_terminal_id'],
                    $transaction['transaction_id']
                );
                $kaspiTerminal->unlockTerminal($transaction['kaspi_terminal_id']);
            } catch (Exception $e) {
                error_log("Cancel payment error: " . $e->getMessage());
            }
        }

        $this->db->update(
            'transactions',
            ['status' => 'cancelled'],
            'id = ?',
            [$transaction['id']]
        );

        $this->addLog($transaction['id'], 'cancelled', 'cancelled', 'Транзакция отменена');

        return true;
    }

    /**
     * Отправка webhook уведомления используя класс Webhook
     */
    private function sendWebhook($id) {
        $transaction = $this->getById($id);

        if (!$transaction['webhook_url']) {
            error_log("WEBHOOK: No webhook_url for transaction #{$id}");
            return false;
        }

        // Используем новый класс Webhook для отправки
        $webhook = new Webhook();
        
        // Определяем тип события
        $eventType = Webhook::EVENT_PAID;
        if ($transaction['status'] === 'partially_paid') {
            $eventType = Webhook::EVENT_PARTIALLY_PAID;
        } elseif ($transaction['status'] === 'failed') {
            $eventType = Webhook::EVENT_FAILED;
        } elseif ($transaction['status'] === 'cancelled') {
            $eventType = Webhook::EVENT_CANCELLED;
        }
        
        // Отправляем webhook
        $success = $webhook->send($id, $eventType);
        
        error_log("WEBHOOK: Sent {$eventType} webhook for transaction #{$id}, success: " . ($success ? 'yes' : 'no'));
        
        return $success;
    }

    /**
     * Получение транзакции по ID
     */
    public function getById($id) {
        return $this->db->fetch("SELECT * FROM transactions WHERE id = ?", [$id]);
    }

    /**
     * Получение транзакции по transaction_id
     */
    public function getByTransactionId($transactionId) {
        return $this->db->fetch("SELECT * FROM transactions WHERE transaction_id = ?", [$transactionId]);
    }

    /**
     * Получение списка транзакций с фильтрами
     */
    public function getList($filters = []) {
        $where = [];
        $params = [];

        if (isset($filters['source_id'])) {
            $where[] = 'source_id = ?';
            $params[] = $filters['source_id'];
        }

        if (isset($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        if (isset($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;

        $sql = "SELECT * FROM transactions $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Добавление лога
     */
    private function addLog($transactionId, $action, $status = null, $message = null, $data = null) {
        $this->db->insert('transaction_logs', [
            'transaction_id' => $transactionId,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'request_data' => $data ? json_encode($data) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Отслеживание посещения страницы оплаты клиентом
     */
    public function trackVisit($transactionId) {
        $transaction = $this->getByTransactionId($transactionId);
        
        if (!$transaction) {
            throw new Exception("Транзакция не найдена");
        }

        // Собираем данные о клиенте
        $clientData = $this->getClientInfo();

        // Обновляем транзакцию только если еще не было посещения
        if (!$transaction['payment_started_at']) {
            $this->db->update(
                'transactions',
                [
                    'client_ip' => $clientData['ip'],
                    'client_user_agent' => $clientData['user_agent'],
                    'client_device' => $clientData['device'],
                    'client_browser' => $clientData['browser'],
                    'client_os' => $clientData['os'],
                    'client_country' => $clientData['country'],
                    'payment_started_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$transaction['id']]
            );

            $this->addLog(
                $transaction['id'],
                'visit_tracked',
                null,
                'Клиент открыл страницу оплаты',
                $clientData
            );
        }

        return true;
    }

    /**
     * Получение информации о клиенте
     */
    private function getClientInfo() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $this->getClientIP();

        return [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'device' => $this->detectDevice($userAgent),
            'browser' => $this->detectBrowser($userAgent),
            'os' => $this->detectOS($userAgent),
            'country' => $this->detectCountry($ip)
        ];
    }

    /**
     * Получение реального IP адреса клиента
     */
    private function getClientIP() {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Определение типа устройства
     */
    private function detectDevice($userAgent) {
        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }
        
        if (preg_match('/mobile|android|iphone|ipod|blackberry|windows phone/i', $userAgent)) {
            return 'mobile';
        }
        
        return 'desktop';
    }

    /**
     * Определение браузера
     */
    private function detectBrowser($userAgent) {
        if (preg_match('/Edge/i', $userAgent)) return 'Microsoft Edge';
        if (preg_match('/Chrome/i', $userAgent)) return 'Google Chrome';
        if (preg_match('/Safari/i', $userAgent)) return 'Safari';
        if (preg_match('/Firefox/i', $userAgent)) return 'Mozilla Firefox';
        if (preg_match('/MSIE|Trident/i', $userAgent)) return 'Internet Explorer';
        if (preg_match('/Opera|OPR/i', $userAgent)) return 'Opera';
        
        return 'Unknown';
    }

    /**
     * Определение операционной системы
     */
    private function detectOS($userAgent) {
        if (preg_match('/windows nt 10/i', $userAgent)) return 'Windows 10';
        if (preg_match('/windows nt 11/i', $userAgent)) return 'Windows 11';
        if (preg_match('/windows/i', $userAgent)) return 'Windows';
        if (preg_match('/macintosh|mac os x/i', $userAgent)) return 'MacOS';
        if (preg_match('/linux/i', $userAgent)) return 'Linux';
        if (preg_match('/android/i', $userAgent)) return 'Android';
        if (preg_match('/iphone|ipad|ipod/i', $userAgent)) return 'iOS';
        
        return 'Unknown';
    }

    /**
     * Определение страны по IP (упрощенная версия)
     */
    private function detectCountry($ip) {
        // Здесь можно использовать GeoIP базу данных или API
        // Для простоты возвращаем null
        // В будущем можно интегрировать с ipinfo.io, ip-api.com и т.д.
        return null;
    }

    /**
     * Генерация уникального ID транзакции
     */
    private function generateTransactionId() {
        return strtoupper(bin2hex(random_bytes(TRANSACTION_ID_LENGTH / 2)));
    }

    /**
     * Получение способа оплаты
     */
    private function getPaymentMethod($id) {
        return $this->db->fetch("SELECT * FROM payment_methods WHERE id = ?", [$id]);
    }

    /**
     * Вычисление процента комиссии
     */
    private function getCommissionPercent($paymentMethod, $type = 'card') {
        if ($type === 'credit' && $paymentMethod['has_credit']) {
            return $paymentMethod['credit_commission_percent'];
        }

        if ($type === 'installment' && $paymentMethod['has_installment']) {
            return $paymentMethod['installment_commission_percent'];
        }

        return $paymentMethod['commission_percent'];
    }

    /**
     * ЕДИНЫЙ метод расчета комиссии для всех случаев
     * Гарантирует идентичный расчет при создании платежа и при обработке
     * 
     * @param array $paymentMethod Способ оплаты
     * @param float $amount Базовая сумма (без комиссии)
     * @param string|null $paymentType Тип оплаты (credit, installment, card и т.д.)
     * @return array ['commission' => float, 'total' => float, 'percent' => float]
     */
    private function calculateCommission($paymentMethod, $amount, $paymentType = null) {
        if (!$paymentMethod) {
            return [
                'commission' => 0,
                'total' => $amount,
                'percent' => 0
            ];
        }

        // Определяем процент комиссии
        $commissionPercent = 0;
        
        if ($paymentType === 'credit' && $paymentMethod['has_credit']) {
            $commissionPercent = $paymentMethod['credit_commission_percent'];
        } elseif ($paymentType === 'installment' && $paymentMethod['has_installment']) {
            $commissionPercent = $paymentMethod['installment_commission_percent'];
        } else {
            // Если не указан тип, проверяем характеристики способа оплаты
            if ($paymentMethod['has_credit']) {
                $commissionPercent = $paymentMethod['credit_commission_percent'];
            } elseif ($paymentMethod['has_installment']) {
                $commissionPercent = $paymentMethod['installment_commission_percent'];
            } else {
                $commissionPercent = $paymentMethod['commission_percent'];
            }
        }

        // Вычисляем комиссию
        $commissionAmount = 0;
        $totalAmount = $amount;

        // Для кредита и рассрочки ВСЕГДА добавляем комиссию к сумме
        // Для обычных способов - только если включена настройка add_commission_to_amount
        $shouldAddCommission = 
            $paymentMethod['has_credit'] || 
            $paymentMethod['has_installment'] || 
            $paymentMethod['add_commission_to_amount'];

        if ($shouldAddCommission && $commissionPercent > 0) {
            // Округляем до 2 знаков после запятой для точности
            $commissionAmount = round($amount * ($commissionPercent / 100), 2);
            $totalAmount = round($amount + $commissionAmount, 2);
        }

        return [
            'commission' => $commissionAmount,
            'total' => $totalAmount,
            'percent' => $commissionPercent
        ];
    }
}
