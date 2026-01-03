<?php
/**
 * Payment Endpoints (Public API)
 */

$transaction = new Transaction();

// Публичные эндпоинты (не требуют авторизации)
$publicEndpoints = [
    'GET:/api/payment/' => true, // GET /api/payment/{id}
    'GET:/api/payment-methods/active' => true,
    'POST:/api/payment/' => false, // только для track-visit
];

// Проверяем, является ли это публичным эндпоинтом
$isPublicEndpoint = false;
$currentPath = '/api/' . implode('/', array_slice($pathParts, 0, 2));

// GET /api/payment/{id} или /api/payment/{id}/status - публичные
if ($requestMethod === 'GET' && isset($pathParts[1]) && !empty($pathParts[1])) {
    $isPublicEndpoint = true;
}

// GET /api/payment-methods/active - публичный
if ($requestMethod === 'GET' && $pathParts[0] === 'payment-methods' && ($pathParts[1] ?? '') === 'active') {
    $isPublicEndpoint = true;
}

// POST /api/payment/{id}/track-visit - публичный
if ($requestMethod === 'POST' && isset($pathParts[1]) && ($pathParts[2] ?? '') === 'track-visit') {
    $isPublicEndpoint = true;
}

// POST /api/payment/{id}/kaspi - публичный (клиент инициирует оплату)
if ($requestMethod === 'POST' && isset($pathParts[1]) && ($pathParts[2] ?? '') === 'kaspi') {
    $isPublicEndpoint = true;
}

// Если не публичный эндпоинт, проверяем API токен
if (!$isPublicEndpoint) {
$apiToken = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
$auth = new Auth();
$source = $auth->validateApiToken($apiToken);

if (!$source) {
    Response::unauthorized('Invalid API token');
}
}

// GET /api/payment-methods - Получение активных способов оплаты (ПУБЛИЧНЫЙ)
// GET /api/payment-methods/active - Алиас для совместимости
if ($requestMethod === 'GET' && $pathParts[0] === 'payment-methods' && (!isset($pathParts[1]) || $pathParts[1] === 'active')) {
    try {
        $db = Database::getInstance();
        $methods = $db->fetchAll(
            "SELECT id, code, name, type, provider, country_id, 
                    allowed_countries, payment_currency,
                    icon_url, icon_emoji,
                    commission_percent, 
                    has_credit, credit_commission_percent, 
                    has_installment, installment_months, installment_commission_percent,
                    add_commission_to_amount, sort_order
             FROM payment_methods 
             WHERE is_active = 1 
             ORDER BY sort_order ASC, id ASC"
        );
        Response::success($methods);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/payment/init - Создание платежа
elseif ($requestMethod === 'POST' && $pathParts[1] === 'init') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Валидация
    if (!isset($data['amount']) || $data['amount'] <= 0) {
        Response::error('Сумма должна быть больше 0', 400);
    }

    try {
        $result = $transaction->create($source['source_id'], $data);
        Response::success([
            'transaction_id' => $result['transaction_id'],
            'amount' => $result['amount'],
            'currency' => $result['currency'],
            'status' => $result['status'],
            'expires_at' => $result['expires_at']
        ], 'Платеж создан', 201);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// GET /api/payment/{id} - Информация о платеже
elseif ($requestMethod === 'GET' && $pathParts[0] === 'payment' && isset($pathParts[1])) {
    try {
        $result = $transaction->getByTransactionId($pathParts[1]);
        
        if (!$result) {
            Response::notFound('Транзакция не найдена');
        }

        Response::success([
            'transaction_id' => $result['transaction_id'],
            'amount' => $result['amount'],
            'currency' => $result['currency'],
            'status' => $result['status'],
            'description' => $result['description'],
            'payment_url' => $result['payment_url'],
            'qr_code' => $result['qr_code'],
            'paid_amount' => $result['paid_amount'],
            'remaining_amount' => $result['remaining_amount'],
            'created_at' => $result['created_at'],
            'paid_at' => $result['paid_at']
        ]);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/payment/{id}/kaspi - Обработка через Kaspi
elseif ($requestMethod === 'POST' && isset($pathParts[1]) && $pathParts[2] === 'kaspi') {
    $data = json_decode(file_get_contents('php://input'), true);
    $paymentMethod = $data['payment_method'] ?? 'kaspi_gold';

    try {
        $result = $transaction->processKaspiPayment($pathParts[1], $paymentMethod);
        Response::success($result, 'Платеж инициирован');
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// GET /api/payment/{id}/status - Проверка статуса платежа
elseif ($requestMethod === 'GET' && isset($pathParts[1]) && $pathParts[2] === 'status') {
    try {
        $result = $transaction->checkStatus($pathParts[1]);
        Response::success($result);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/payment/{id}/cancel - Отмена платежа
elseif ($requestMethod === 'POST' && isset($pathParts[1]) && $pathParts[2] === 'cancel') {
    try {
        $transaction->cancel($pathParts[1]);
        Response::success(null, 'Платеж отменен');
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/payment/{id}/track-visit - Отслеживание посещения страницы оплаты
elseif ($requestMethod === 'POST' && isset($pathParts[1]) && $pathParts[2] === 'track-visit') {
    try {
        $transaction->trackVisit($pathParts[1]);
        Response::success(null, 'Visit tracked');
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

else {
    Response::notFound('Payment endpoint not found');
}
