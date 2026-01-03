<?php
/**
 * Admin Panel Endpoints (Requires JWT Authentication)
 */

// Проверка JWT токена - читаем из разных источников
$authHeader = null;

// Пытаемся получить Authorization header из разных мест
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    }
}

$token = str_replace('Bearer ', '', $authHeader ?? '');

$auth = new Auth();
$admin = $auth->validateToken($token);

if (!$admin) {
    Response::unauthorized('Требуется авторизация');
}

$db = Database::getInstance();

// ===== АДМИНЫ =====

// GET /api/admin/admins - Список админов
if ($requestMethod === 'GET' && $pathParts[1] === 'admins' && !isset($pathParts[2])) {
    $admins = $db->fetchAll("SELECT id, phone, country_code, country_name, name, allowed_countries, allowed_payment_systems, is_super_admin, is_active, last_login, created_at FROM admins ORDER BY id DESC");
    
    foreach ($admins as &$item) {
        $item['allowed_countries'] = json_decode($item['allowed_countries'], true);
        $item['allowed_payment_systems'] = json_decode($item['allowed_payment_systems'], true);
    }
    
    Response::success($admins);
}

// GET /api/admin/admins/{id} - Один админ
elseif ($requestMethod === 'GET' && $pathParts[1] === 'admins' && isset($pathParts[2])) {
    $adminData = $db->fetch("SELECT id, phone, country_code, country_name, name, allowed_countries, allowed_payment_systems, is_super_admin, is_active, last_login, created_at FROM admins WHERE id = ?", [$pathParts[2]]);
    
    if (!$adminData) {
        Response::notFound('Админ не найден');
    }
    
    $adminData['allowed_countries'] = json_decode($adminData['allowed_countries'], true);
    $adminData['allowed_payment_systems'] = json_decode($adminData['allowed_payment_systems'], true);
    
    Response::success($adminData);
}

// POST /api/admin/admins - Создание админа
elseif ($requestMethod === 'POST' && $pathParts[1] === 'admins') {
    if (!$admin['is_super_admin']) {
        Response::forbidden('Только супер-админ может создавать админов');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['phone'])) {
        Response::error('Номер телефона обязателен', 400);
    }

    try {
        $id = $db->insert('admins', [
            'phone' => $data['phone'],
            'country_code' => $data['country_code'] ?? '+7',
            'country_name' => $data['country_name'] ?? null,
            'name' => $data['name'] ?? null,
            'allowed_countries' => isset($data['allowed_countries']) ? json_encode($data['allowed_countries']) : null,
            'allowed_payment_systems' => isset($data['allowed_payment_systems']) ? json_encode($data['allowed_payment_systems']) : null,
            'is_super_admin' => $data['is_super_admin'] ?? 0,
            'is_active' => $data['is_active'] ?? 1
        ]);

        Response::success(['id' => $id], 'Админ создан', 201);
    } catch (Exception $e) {
        Response::error('Ошибка создания админа: ' . $e->getMessage(), 400);
    }
}

// PUT /api/admin/admins/{id} - Обновление админа
elseif ($requestMethod === 'PUT' && $pathParts[1] === 'admins' && isset($pathParts[2])) {
    if (!$admin['is_super_admin'] && $admin['admin_id'] != $pathParts[2]) {
        Response::forbidden('Недостаточно прав');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $updateData = [];

    if (isset($data['name'])) $updateData['name'] = $data['name'];
    if (isset($data['country_code'])) $updateData['country_code'] = $data['country_code'];
    if (isset($data['country_name'])) $updateData['country_name'] = $data['country_name'];
    if (isset($data['allowed_countries'])) $updateData['allowed_countries'] = json_encode($data['allowed_countries']);
    if (isset($data['allowed_payment_systems'])) $updateData['allowed_payment_systems'] = json_encode($data['allowed_payment_systems']);
    
    if ($admin['is_super_admin']) {
        if (isset($data['is_super_admin'])) $updateData['is_super_admin'] = $data['is_super_admin'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];
    }

    try {
        $db->update('admins', $updateData, 'id = ?', [$pathParts[2]]);
        Response::success(null, 'Админ обновлен');
    } catch (Exception $e) {
        Response::error('Ошибка обновления: ' . $e->getMessage(), 400);
    }
}

// DELETE /api/admin/admins/{id} - Удаление админа
elseif ($requestMethod === 'DELETE' && $pathParts[1] === 'admins' && isset($pathParts[2])) {
    if (!$admin['is_super_admin']) {
        Response::forbidden('Только супер-админ может удалять админов');
    }

    if ($admin['admin_id'] == $pathParts[2]) {
        Response::error('Нельзя удалить самого себя', 400);
    }

    $db->delete('admins', 'id = ?', [$pathParts[2]]);
    Response::success(null, 'Админ удален');
}

// ===== ИСТОЧНИКИ =====

// GET /api/admin/sources - Список источников
elseif ($requestMethod === 'GET' && $pathParts[1] === 'sources' && !isset($pathParts[2])) {
    $sources = $db->fetchAll("SELECT * FROM sources ORDER BY id DESC");
    
    foreach ($sources as &$item) {
        $item['allowed_ips'] = json_decode($item['allowed_ips'], true);
    }
    
    Response::success($sources);
}

// POST /api/admin/sources - Создание источника
elseif ($requestMethod === 'POST' && $pathParts[1] === 'sources') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['name'])) {
        Response::error('Название обязательно', 400);
    }

    try {
        $apiToken = Auth::generateToken();

        $id = $db->insert('sources', [
            'name' => $data['name'],
            'type' => $data['type'] ?? 'website',
            'description' => $data['description'] ?? null,
            'api_token' => $apiToken,
            'webhook_url' => $data['webhook_url'] ?? null,
            'allowed_ips' => isset($data['allowed_ips']) ? json_encode($data['allowed_ips']) : null,
            'is_active' => $data['is_active'] ?? 1,
            'created_by' => $admin['admin_id']
        ]);

        Response::success([
            'id' => $id,
            'api_token' => $apiToken
        ], 'Источник создан', 201);
    } catch (Exception $e) {
        Response::error('Ошибка создания: ' . $e->getMessage(), 400);
    }
}

// PUT /api/admin/sources/{id} - Обновление источника
elseif ($requestMethod === 'PUT' && $pathParts[1] === 'sources' && isset($pathParts[2])) {
    $data = json_decode(file_get_contents('php://input'), true);
    $updateData = [];

    if (isset($data['name'])) $updateData['name'] = $data['name'];
    if (isset($data['type'])) $updateData['type'] = $data['type'];
    if (isset($data['description'])) $updateData['description'] = $data['description'];
    if (isset($data['webhook_url'])) $updateData['webhook_url'] = $data['webhook_url'];
    if (isset($data['allowed_ips'])) $updateData['allowed_ips'] = json_encode($data['allowed_ips']);
    if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];

    try {
        $db->update('sources', $updateData, 'id = ?', [$pathParts[2]]);
        Response::success(null, 'Источник обновлен');
    } catch (Exception $e) {
        Response::error('Ошибка обновления: ' . $e->getMessage(), 400);
    }
}

// DELETE /api/admin/sources/{id} - Удаление источника
elseif ($requestMethod === 'DELETE' && $pathParts[1] === 'sources' && isset($pathParts[2])) {
    $db->delete('sources', 'id = ?', [$pathParts[2]]);
    Response::success(null, 'Источник удален');
}

// ===== СПОСОБЫ ОПЛАТЫ =====

// GET /api/admin/payment-methods - Список способов оплаты
elseif ($requestMethod === 'GET' && $pathParts[1] === 'payment-methods' && !isset($pathParts[2])) {
    $methods = $db->fetchAll("SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC");
    
    foreach ($methods as &$item) {
        $item['settings'] = json_decode($item['settings'], true);
    }
    
    Response::success($methods);
}

// PUT /api/admin/payment-methods/{id} - Обновление способа оплаты
elseif ($requestMethod === 'PUT' && $pathParts[1] === 'payment-methods' && isset($pathParts[2])) {
    $data = json_decode(file_get_contents('php://input'), true);
    $updateData = [];

    $allowedFields = [
        'name', 'commission_percent', 'has_credit', 'credit_commission_percent',
        'has_installment', 'installment_months', 'installment_commission_percent',
        'add_commission_to_amount', 'is_active', 'sort_order'
    ];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateData[$field] = $data[$field];
        }
    }

    if (isset($data['settings'])) {
        $updateData['settings'] = json_encode($data['settings']);
    }

    try {
        $db->update('payment_methods', $updateData, 'id = ?', [$pathParts[2]]);
        Response::success(null, 'Способ оплаты обновлен');
    } catch (Exception $e) {
        Response::error('Ошибка обновления: ' . $e->getMessage(), 400);
    }
}

// ===== ТЕРМИНАЛЫ =====

// POST /api/admin/terminals/check-all - Проверка всех терминалов (ДОЛЖЕН БЫТЬ ПЕРВЫМ!)
if ($requestMethod === 'POST' && $pathParts[1] === 'terminals' && isset($pathParts[2]) && $pathParts[2] === 'check-all') {
    $kaspiTerminal = new KaspiTerminal();
    $results = [];
    $onlineCount = 0;
    $offlineCount = 0;
    
    $terminals = $kaspiTerminal->getAllTerminals();
    
    foreach ($terminals as $terminal) {
        if (!$terminal['is_active']) continue;
        
        try {
            $status = $kaspiTerminal->checkTerminalStatus($terminal['id']);
            $results[] = [
                'terminal_id' => $terminal['id'],
                'name' => $terminal['name'],
                'status' => $status['status'],
                'message' => $status['message']
            ];
            
            if ($status['status'] === 'online') {
                $onlineCount++;
            } else {
                $offlineCount++;
            }
        } catch (Exception $e) {
            $results[] = [
                'terminal_id' => $terminal['id'],
                'name' => $terminal['name'],
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            $offlineCount++;
        }
    }
    
    Response::success([
        'results' => $results,
        'total' => count($terminals),
        'online' => $onlineCount,
        'offline' => $offlineCount
    ]);
}

// GET /api/admin/terminals - Список терминалов
elseif ($requestMethod === 'GET' && $pathParts[1] === 'terminals' && !isset($pathParts[2])) {
    $kaspiTerminal = new KaspiTerminal();
    $terminals = $kaspiTerminal->getAllTerminals();
    Response::success($terminals);
}

// GET /api/admin/terminals/{id}/status - Проверка статуса терминала
elseif ($requestMethod === 'GET' && $pathParts[1] === 'terminals' && isset($pathParts[2]) && isset($pathParts[3]) && $pathParts[3] === 'status') {
    $kaspiTerminal = new KaspiTerminal();
    $status = $kaspiTerminal->checkTerminalStatus($pathParts[2]);
    Response::success($status);
}

// POST /api/admin/terminals - Добавление терминала
elseif ($requestMethod === 'POST' && $pathParts[1] === 'terminals') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['ip_address']) || !isset($data['port'])) {
        Response::error('IP адрес и порт обязательны', 400);
    }

    try {
        $kaspiTerminal = new KaspiTerminal();
        $id = $kaspiTerminal->addTerminal($data);
        Response::success(['id' => $id], 'Терминал добавлен', 201);
    } catch (Exception $e) {
        Response::error('Ошибка добавления: ' . $e->getMessage(), 400);
    }
}

// PUT /api/admin/terminals/{id} - Обновление терминала
elseif ($requestMethod === 'PUT' && $pathParts[1] === 'terminals' && isset($pathParts[2])) {
    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $kaspiTerminal = new KaspiTerminal();
        $kaspiTerminal->updateTerminal($pathParts[2], $data);
        Response::success(null, 'Терминал обновлен');
    } catch (Exception $e) {
        Response::error('Ошибка обновления: ' . $e->getMessage(), 400);
    }
}

// DELETE /api/admin/terminals/{id} - Удаление терминала
elseif ($requestMethod === 'DELETE' && $pathParts[1] === 'terminals' && isset($pathParts[2])) {
    $kaspiTerminal = new KaspiTerminal();
    $kaspiTerminal->deleteTerminal($pathParts[2]);
    Response::success(null, 'Терминал удален');
}

// ===== ТРАНЗАКЦИИ =====

// GET /api/admin/transactions - Список транзакций
elseif ($requestMethod === 'GET' && $pathParts[1] === 'transactions' && !isset($pathParts[2])) {
    $filters = [
        'source_id' => $_GET['source_id'] ?? null,
        'status' => $_GET['status'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'limit' => $_GET['limit'] ?? 100,
        'offset' => $_GET['offset'] ?? 0
    ];

    $transaction = new Transaction();
    $transactions = $transaction->getList($filters);
    Response::success($transactions);
}

// DELETE /api/admin/transactions/{id} - Удаление транзакции
elseif ($requestMethod === 'DELETE' && $pathParts[1] === 'transactions' && isset($pathParts[2])) {
    try {
        $db->delete('transactions', 'id = ?', [$pathParts[2]]);
        Response::success(null, 'Транзакция удалена');
    } catch (Exception $e) {
        Response::error('Ошибка удаления: ' . $e->getMessage(), 400);
    }
}

// GET /admin/transactions/{id}/partial-payments - История частичных оплат
elseif ($requestMethod === 'GET' && $pathParts[1] === 'transactions' && isset($pathParts[2]) && $pathParts[3] === 'partial-payments') {
    try {
        $transactionId = $pathParts[2];
        $partialPayments = $db->fetchAll(
            "SELECT * FROM partial_payments WHERE transaction_id = ? ORDER BY paid_at ASC",
            [$transactionId]
        );
        Response::success($partialPayments);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// GET /admin/partial-payments/{id}/refund-status - Проверка статуса возврата
elseif ($requestMethod === 'GET' && $pathParts[1] === 'partial-payments' && isset($pathParts[2]) && $pathParts[3] === 'refund-status') {
    try {
        $partialPaymentId = $pathParts[2];
        
        // Получаем данные частичной оплаты с данными о возврате
        $partialPayment = $db->fetch(
            "SELECT pp.*, t.kaspi_terminal_id
             FROM partial_payments pp
             JOIN transactions t ON pp.transaction_id = t.id
             WHERE pp.id = ?",
            [$partialPaymentId]
        );
        
        if (!$partialPayment) {
            Response::notFound('Операция не найдена');
        }
        
        // Если уже возвращено - возвращаем статус
        if ($partialPayment['is_refunded']) {
            Response::success([
                'status' => 'completed',
                'is_refunded' => true,
                'refunded_at' => $partialPayment['refunded_at']
            ]);
        }
        
        // Если нет process_id - возврат не инициирован
        if (!$partialPayment['refund_process_id']) {
            Response::success([
                'status' => 'not_initiated',
                'is_refunded' => false
            ]);
        }
        
        // Проверяем статус на терминале
        $kaspiTerminal = new KaspiTerminal();
        $status = $kaspiTerminal->checkPaymentStatus(
            $partialPayment['kaspi_terminal_id'],
            $partialPayment['refund_process_id']
        );
        
        error_log("REFUND STATUS CHECK: Payment #{$partialPaymentId}, Status: " . json_encode($status));
        
        // Если возврат успешно завершен
        if ($status['status'] === 'success' || $status['status'] === 'paid') {
            // Обновляем partial_payment
            $db->update(
                'partial_payments',
                [
                    'is_refunded' => 1,
                    'refund_transaction_id' => $status['transaction_id'] ?? null,
                    'refund_details' => json_encode($status['details'] ?? []),
                    'refunded_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$partialPaymentId]
            );
            
            // Пересчитываем транзакцию
            $transaction = $db->fetch("SELECT * FROM transactions WHERE id = ?", [$partialPayment['transaction_id']]);
            
            // Получаем все неотмененные частичные оплаты
            $validPayments = $db->fetchAll(
                "SELECT * FROM partial_payments WHERE transaction_id = ? AND is_refunded = 0",
                [$partialPayment['transaction_id']]
            );
            
            // Пересчитываем суммы
            $totalPaid = 0;
            $totalReceived = 0;
            
            foreach ($validPayments as $payment) {
                $totalPaid += floatval($payment['amount']);
                $commission = floatval($payment['commission_amount'] ?? 0);
                $totalReceived += (floatval($payment['amount']) - $commission);
            }
            
            $originalAmount = floatval($transaction['original_amount']);
            $remaining = $originalAmount - $totalReceived;
            
            // Определяем новый статус
            $newStatus = 'pending';
            if (count($validPayments) === 0) {
                $newStatus = 'pending';
                $needsAdditionalPayment = 0;
            } elseif ($remaining <= 10) {
                $newStatus = 'paid';
                $needsAdditionalPayment = 0;
            } elseif ($totalReceived > 0) {
                $newStatus = 'partially_paid';
                $needsAdditionalPayment = 1;
            }
            
            // Обновляем транзакцию
            $db->update(
                'transactions',
                [
                    'status' => $newStatus,
                    'paid_amount' => $totalPaid,
                    'actual_amount_received' => $totalReceived,
                    'remaining_amount' => max(0, $remaining),
                    'needs_additional_payment' => $needsAdditionalPayment
                ],
                'id = ?',
                [$partialPayment['transaction_id']]
            );
            
            error_log("REFUND COMPLETED: Transaction updated - status: {$newStatus}, remaining: {$remaining}");
            
            // Отправляем вебхук о возврате
            if (!empty($transaction['webhook_url'])) {
                error_log("REFUND: Sending webhook...");
                
                $webhook = new Webhook();
                $webhookSent = $webhook->send($partialPayment['transaction_id'], Webhook::EVENT_REFUNDED, [
                    'refunded_amount' => floatval($partialPayment['amount']),
                    'refund_id' => $partialPaymentId,
                    'remaining_amount' => $remaining
                ]);
                
                error_log("REFUND: Webhook " . ($webhookSent ? "sent successfully" : "failed"));
            }
            
            Response::success([
                'status' => 'completed',
                'is_refunded' => true,
                'refunded_at' => date('Y-m-d H:i:s'),
                'transaction_status' => $newStatus,
                'total_paid' => $totalPaid,
                'total_received' => $totalReceived,
                'remaining' => $remaining
            ]);
        }
        
        // Если в процессе или ожидает
        Response::success([
            'status' => $status['status'] ?? 'waiting',
            'is_refunded' => false
        ]);
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /admin/partial-payments/{id}/refund - Возврат платежа (отмена операции)
elseif ($requestMethod === 'POST' && $pathParts[1] === 'partial-payments' && isset($pathParts[2]) && $pathParts[3] === 'refund') {
    try {
        $partialPaymentId = $pathParts[2];
        
        // Получаем данные частичной оплаты
        $partialPayment = $db->fetch(
            "SELECT pp.*, t.kaspi_terminal_id, t.transaction_id, t.amount as transaction_amount,
                    t.terminal_transaction_id, t.terminal_product_type
             FROM partial_payments pp
             JOIN transactions t ON pp.transaction_id = t.id
             WHERE pp.id = ?",
            [$partialPaymentId]
        );
        
        if (!$partialPayment) {
            Response::notFound('Операция оплаты не найдена');
        }
        
        // Проверяем, что еще не было возврата
        if ($partialPayment['is_refunded']) {
            Response::error('Возврат уже был произведен ранее', 400);
        }
        
        // Проверяем что есть привязка к терминалу
        if (!$partialPayment['kaspi_terminal_id']) {
            Response::error('Операция не привязана к терминалу Kaspi. Возврат невозможен.', 400);
        }
        
        // Извлекаем детали оплаты из payment_details (JSON)
        $paymentDetails = json_decode($partialPayment['payment_details'] ?? '{}', true);
        
        // Определяем метод оплаты согласно документации Kaspi (qr, card, alaqan)
        $productType = $paymentDetails['addInfo']['ProductType'] ?? $partialPayment['terminal_product_type'] ?? 'Gold';
        
        // Kaspi использует ProductType: Gold, Red, Credit, Installment, Loan
        // Нужно определить был ли это QR или карта
        // Если есть orderNumber - это QR, если нет - карта
        $method = 'qr'; // по умолчанию QR
        if (!empty($paymentDetails['chequeInfo']['orderNumber']) || $productType === 'Gold' || $productType === 'Red') {
            $method = 'qr';
        } else {
            $method = 'card';
        }
        
        // ID транзакции от терминала
        $terminalTransactionId = $paymentDetails['transactionId'] 
                              ?? $partialPayment['terminal_transaction_id'] 
                              ?? null;
        
        if (empty($terminalTransactionId)) {
            Response::error('Не найден ID транзакции от терминала. Возврат невозможен без transactionId.', 400);
        }
        
        // Инициируем возврат на терминале
        $kaspiTerminal = new KaspiTerminal();
        
        $refundAmount = floatval($partialPayment['amount']);
        $terminalId = $partialPayment['kaspi_terminal_id'];
        
        error_log("REFUND: Starting refund for partial_payment #{$partialPaymentId}");
        error_log("REFUND: Amount: {$refundAmount}, Terminal: {$terminalId}");
        error_log("REFUND: Method: {$method}, TransactionId: {$terminalTransactionId}");
        
        try {
            // Вызываем метод возврата на терминале согласно документации Kaspi
            $refundResult = $kaspiTerminal->refundPayment(
                $terminalId,
                $refundAmount,
                $method,
                $terminalTransactionId
            );
            
            error_log("REFUND: Terminal refund initiated - " . json_encode($refundResult));
            
            // Проверяем, требуется ли подтверждение от клиента
            $requiresConfirmation = !empty($refundResult['requires_client_confirmation']);
            $qrCode = $refundResult['qr_code'] ?? null;  // Оригинальный QR (qr.kaspi.kz) - для изображения
            $paymentUrl = $refundResult['payment_url'] ?? null; // Ссылка (pay.kaspi.kz/pay) - для клика
            
            // Обновляем запись о частичной оплате
            // Возврат еще не завершен - ждет подтверждения от клиента
            $db->update(
                'partial_payments',
                [
                    'is_refunded' => 0, // Пока не отменено - ждет подтверждения клиента
                    'refund_amount' => $refundResult['refund_amount'] ?? $refundAmount,
                    'refund_terminal_id' => $terminalId,
                    'refund_process_id' => $refundResult['process_id'] ?? null,
                    'refund_qr_code' => $qrCode, // Оригинальный QR для сканирования
                    'refund_transaction_id' => $refundResult['refund_transaction_id'] ?? null,
                    'refund_details' => isset($refundResult) ? json_encode($refundResult) : null,
                    'refunded_at' => null, // Пока не завершено
                    'refunded_by_admin_id' => $admin['admin_id']
                ],
                'id = ?',
                [$partialPaymentId]
            );
            
            // Пересчитываем итоги транзакции
            $transaction = $db->fetch("SELECT * FROM transactions WHERE id = ?", [$partialPayment['transaction_id']]);
            
            // Получаем все неотмененные частичные оплаты
            $validPayments = $db->fetchAll(
                "SELECT * FROM partial_payments WHERE transaction_id = ? AND is_refunded = 0",
                [$partialPayment['transaction_id']]
            );
            
            // Пересчитываем суммы
            $totalPaid = 0;
            $totalReceived = 0;
            $totalCommission = 0;
            
            foreach ($validPayments as $payment) {
                $totalPaid += floatval($payment['amount']);
                $commission = floatval($payment['commission_amount'] ?? 0);
                $totalCommission += $commission;
                $totalReceived += (floatval($payment['amount']) - $commission);
            }
            
            $originalAmount = floatval($transaction['original_amount']);
            $remaining = $originalAmount - $totalReceived;
            
            // Определяем новый статус транзакции
            $newStatus = 'pending';
            if (count($validPayments) === 0) {
                // Все возвраты - возвращаем в pending
                $newStatus = 'pending';
                $needsAdditionalPayment = 0;
            } elseif ($remaining <= 10) {
                $newStatus = 'paid';
                $needsAdditionalPayment = 0;
            } elseif ($totalReceived > 0) {
                $newStatus = 'partially_paid';
                $needsAdditionalPayment = 1;
            }
            
            // Обновляем транзакцию
            $db->update(
                'transactions',
                [
                    'status' => $newStatus,
                    'paid_amount' => $totalPaid,
                    'actual_amount_received' => $totalReceived,
                    'remaining_amount' => max(0, $remaining),
                    'needs_additional_payment' => $needsAdditionalPayment
                ],
                'id = ?',
                [$partialPayment['transaction_id']]
            );
            
            error_log("REFUND: Transaction status NOT updated yet - waiting for client confirmation");
            
            // Формируем ответ с QR кодом и ссылкой для клиента
            $message = $requiresConfirmation 
                ? 'Возврат инициирован. Передайте QR код клиенту для подтверждения возврата.'
                : 'Возврат успешно выполнен';
            
            Response::success([
                'refund_qr_code' => $qrCode,  // Оригинальный QR (qr.kaspi.kz) - для изображения
                'refund_payment_url' => $paymentUrl,  // Ссылка (pay.kaspi.kz/pay) - для клика
                'process_id' => $refundResult['process_id'] ?? null,
                'requires_confirmation' => $requiresConfirmation,
                'refund_amount' => $refundAmount,
                'message' => $refundResult['message'] ?? $message,
                'status' => $refundResult['status'] ?? 'waiting_confirmation'
            ], $message);
            
        } catch (Exception $e) {
            error_log("REFUND ERROR: " . $e->getMessage());
            Response::error('Ошибка при выполнении возврата на терминале: ' . $e->getMessage(), 500);
        }
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// === COUNTRIES ENDPOINTS ===

// GET /admin/countries - Список стран
elseif ($requestMethod === 'GET' && $pathParts[1] === 'countries' && !isset($pathParts[2])) {
    try {
        $countries = $db->fetchAll("SELECT * FROM countries ORDER BY sort_order ASC, id ASC");
        Response::success($countries);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /admin/countries - Создание страны
elseif ($requestMethod === 'POST' && $pathParts[1] === 'countries') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $id = $db->insert('countries', $data);
        Response::success(['id' => $id], 'Страна создана', 201);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// PUT /admin/countries/{id} - Обновление страны
elseif ($requestMethod === 'PUT' && $pathParts[1] === 'countries' && isset($pathParts[2])) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $db->update('countries', $data, 'id = ?', [$pathParts[2]]);
        Response::success(null, 'Страна обновлена');
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// DELETE /admin/countries/{id} - Удаление страны
elseif ($requestMethod === 'DELETE' && $pathParts[1] === 'countries' && isset($pathParts[2])) {
    try {
        $db->delete('countries', 'id = ?', [$pathParts[2]]);
        Response::success(null, 'Страна удалена');
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/admin/transactions/{id}/mark-paid - Принудительно отметить как оплаченную
elseif ($requestMethod === 'POST' && $pathParts[1] === 'transactions' && isset($pathParts[2]) && $pathParts[3] === 'mark-paid') {
    try {
        $transactionId = $pathParts[2];
        
        // Получаем транзакцию
        $transaction = $db->fetch("SELECT * FROM transactions WHERE id = ? OR transaction_id = ?", [$transactionId, $transactionId]);
        
        if (!$transaction) {
            Response::error('Транзакция не найдена', 404);
        }
        
        // Обновляем статус (только существующие поля)
        $updateData = [
            'status' => 'paid',
            'remaining_amount' => 0,
            'paid_at' => $transaction['paid_at'] ?? date('Y-m-d H:i:s')
        ];
        
        // Добавляем поле только если оно существует в таблице
        $columns = $db->fetchAll("SHOW COLUMNS FROM transactions LIKE 'needs_additional_payment'");
        if (count($columns) > 0) {
            $updateData['needs_additional_payment'] = 0;
        }
        
        $db->update('transactions', $updateData, 'id = ?', [$transaction['id']]);
        
        // Логируем действие
        $db->insert('transaction_logs', [
            'transaction_id' => $transaction['id'],
            'action' => 'admin_mark_paid',
            'status' => 'paid',
            'message' => 'Администратор вручную отметил транзакцию как полностью оплаченную',
            'request_data' => json_encode([
                'admin_id' => $admin['admin_id'],
                'previous_status' => $transaction['status'],
                'remaining_amount' => $transaction['remaining_amount']
            ]),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        Response::success(null, 'Транзакция отмечена как полностью оплаченная');
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

else {
    Response::notFound('Admin endpoint not found');
}

