<?php
/**
 * Webhook Class
 * Отправка уведомлений на внешние сервисы
 */

class Webhook {
    private $db;
    
    // Типы событий вебхуков
    const EVENT_PAID = 'paid';                      // Полная оплата
    const EVENT_PARTIALLY_PAID = 'partially_paid';  // Частичная оплата
    const EVENT_CANCELLED = 'cancelled';            // Отмена
    const EVENT_FAILED = 'failed';                  // Ошибка
    const EVENT_REFUNDED = 'refunded';              // Возврат

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Отправить вебхук для транзакции
     * 
     * @param int $transactionId ID транзакции
     * @param string $eventType Тип события (paid, partially_paid, cancelled, refunded)
     * @param array $additionalData Дополнительные данные для отправки
     * @return bool Успешно ли отправлен
     */
    public function send($transactionId, $eventType, $additionalData = []) {
        // Получаем транзакцию
        $transaction = $this->db->fetch(
            "SELECT * FROM transactions WHERE id = ?",
            [$transactionId]
        );
        
        if (!$transaction) {
            error_log("WEBHOOK: Transaction #{$transactionId} not found");
            return false;
        }
        
        // Проверяем наличие webhook_url
        if (empty($transaction['webhook_url'])) {
            error_log("WEBHOOK: No webhook_url for transaction #{$transactionId}");
            return false;
        }
        
        $webhookUrl = $transaction['webhook_url'];
        
        // Парсим metadata
        $metadata = [];
        if (!empty($transaction['metadata'])) {
            try {
                $metadata = json_decode($transaction['metadata'], true) ?? [];
            } catch (Exception $e) {
                error_log("WEBHOOK: Failed to parse metadata: " . $e->getMessage());
            }
        }
        
        // Формируем payload для вебхука
        $payload = [
            'event' => $eventType,
            'transaction_id' => $transaction['transaction_id'],
            'amount' => floatval($transaction['amount']),
            'original_amount' => floatval($transaction['original_amount']),
            'paid_amount' => floatval($transaction['paid_amount'] ?? 0),
            'actual_amount_received' => floatval($transaction['actual_amount_received'] ?? 0),
            'remaining_amount' => floatval($transaction['remaining_amount'] ?? 0),
            'currency' => $transaction['currency'],
            'status' => $transaction['status'],
            'description' => $transaction['description'],
            'created_at' => $transaction['created_at'],
            'paid_at' => $transaction['paid_at'],
            'metadata' => $metadata, // Дополнительные данные от клиента
            'timestamp' => time()
        ];
        
        // Добавляем дополнительные данные если есть
        if (!empty($additionalData)) {
            $payload = array_merge($payload, $additionalData);
        }
        
        // Генерируем подпись (HMAC-SHA256)
        $signature = null;
        if (!empty($transaction['webhook_secret'])) {
            $signature = hash_hmac('sha256', json_encode($payload), $transaction['webhook_secret']);
        }
        
        // Номер попытки
        $attemptNumber = intval($transaction['webhook_attempts'] ?? 0) + 1;
        
        error_log("WEBHOOK: Sending {$eventType} webhook for transaction {$transaction['transaction_id']} (attempt #{$attemptNumber})");
        error_log("WEBHOOK: URL: {$webhookUrl}");
        error_log("WEBHOOK: Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));
        
        // Отправляем вебхук
        try {
            $ch = curl_init($webhookUrl);
            
            $headers = [
                'Content-Type: application/json',
                'User-Agent: ByFly-Payment-Center/1.0',
                'X-Webhook-Event: ' . $eventType,
                'X-Webhook-Timestamp: ' . time()
            ];
            
            // Добавляем подпись если есть
            if ($signature) {
                $headers[] = 'X-Webhook-Signature: ' . $signature;
            }
            
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            
            $responseBody = curl_exec($ch);
            $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            $isSuccess = ($responseCode >= 200 && $responseCode < 300);
            
            error_log("WEBHOOK: Response - HTTP {$responseCode}, Body: " . substr($responseBody, 0, 200));
            
            // Сохраняем лог вебхука
            $this->logWebhook(
                $transactionId,
                $eventType,
                $webhookUrl,
                $payload,
                $responseCode,
                $responseBody,
                $isSuccess,
                $curlError,
                $attemptNumber
            );
            
            // Обновляем информацию о вебхуке в транзакции
            $updateData = [
                'webhook_attempts' => $attemptNumber,
                'webhook_sent_at' => date('Y-m-d H:i:s')
            ];
            
            if (!$isSuccess) {
                $updateData['webhook_last_error'] = $curlError ?: "HTTP {$responseCode}: " . substr($responseBody, 0, 200);
            } else {
                $updateData['webhook_last_error'] = null;
            }
            
            $this->db->update('transactions', $updateData, 'id = ?', [$transactionId]);
            
            if ($isSuccess) {
                error_log("WEBHOOK: Successfully sent {$eventType} webhook for transaction {$transaction['transaction_id']}");
            } else {
                error_log("WEBHOOK: Failed to send {$eventType} webhook - HTTP {$responseCode}, Error: {$curlError}");
            }
            
            return $isSuccess;
            
        } catch (Exception $e) {
            error_log("WEBHOOK: Exception while sending webhook: " . $e->getMessage());
            
            // Сохраняем ошибку
            $this->logWebhook(
                $transactionId,
                $eventType,
                $webhookUrl,
                $payload,
                0,
                null,
                false,
                $e->getMessage(),
                $attemptNumber
            );
            
            return false;
        }
    }

    /**
     * Логирование вебхука
     */
    private function logWebhook($transactionId, $eventType, $webhookUrl, $payload, $responseCode, $responseBody, $isSuccess, $errorMessage, $attemptNumber) {
        try {
            $this->db->insert('webhook_logs', [
                'transaction_id' => $transactionId,
                'event_type' => $eventType,
                'webhook_url' => $webhookUrl,
                'request_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'response_code' => $responseCode ?: null,
                'response_body' => $responseBody ? substr($responseBody, 0, 5000) : null,
                'is_success' => $isSuccess ? 1 : 0,
                'error_message' => $errorMessage,
                'attempt_number' => $attemptNumber
            ]);
        } catch (Exception $e) {
            error_log("WEBHOOK LOG: Failed to save log - " . $e->getMessage());
        }
    }

    /**
     * Повторная отправка вебхука (для неудачных попыток)
     * 
     * @param int $transactionId ID транзакции
     * @param string $eventType Тип события
     * @param int $maxAttempts Максимальное количество попыток
     * @return bool
     */
    public function retry($transactionId, $eventType, $maxAttempts = 3) {
        $transaction = $this->db->fetch(
            "SELECT * FROM transactions WHERE id = ?",
            [$transactionId]
        );
        
        if (!$transaction) {
            return false;
        }
        
        $currentAttempts = intval($transaction['webhook_attempts'] ?? 0);
        
        if ($currentAttempts >= $maxAttempts) {
            error_log("WEBHOOK: Max retry attempts ({$maxAttempts}) reached for transaction #{$transactionId}");
            return false;
        }
        
        // Повторная отправка
        return $this->send($transactionId, $eventType);
    }

    /**
     * Получить логи вебхуков для транзакции
     */
    public function getLogs($transactionId) {
        return $this->db->fetchAll(
            "SELECT * FROM webhook_logs WHERE transaction_id = ? ORDER BY created_at DESC",
            [$transactionId]
        );
    }
}





