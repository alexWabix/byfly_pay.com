<?php
/**
 * API Gateway Endpoints (Extended API for external services)
 * Требуют X-API-Token аутентификацию
 */

// Проверка API токена
$apiToken = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
$auth = new Auth();
$source = $auth->validateApiToken($apiToken);

if (!$source) {
    Response::unauthorized('Invalid API token');
}

$db = Database::getInstance();

// GET /api/transactions - История транзакций с фильтрами
if ($requestMethod === 'GET' && $pathParts[0] === 'transactions' && !isset($pathParts[1])) {
    try {
        $where = ["source_id = ?"];
        $params = [$source['source_id']];
        
        // Фильтр по статусу
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $where[] = "status = ?";
            $params[] = $_GET['status'];
        }
        
        // Фильтр по дате от
        if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $_GET['date_from'];
        }
        
        // Фильтр по дате до
        if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $_GET['date_to'];
        }
        
        // Фильтр по сумме от
        if (isset($_GET['amount_from']) && !empty($_GET['amount_from'])) {
            $where[] = "amount >= ?";
            $params[] = floatval($_GET['amount_from']);
        }
        
        // Фильтр по сумме до
        if (isset($_GET['amount_to']) && !empty($_GET['amount_to'])) {
            $where[] = "amount <= ?";
            $params[] = floatval($_GET['amount_to']);
        }
        
        // Лимит и offset для пагинации
        $limit = intval($_GET['limit'] ?? 100);
        $offset = intval($_GET['offset'] ?? 0);
        
        if ($limit > 1000) $limit = 1000; // Максимум 1000 записей
        
        $whereClause = implode(' AND ', $where);
        
        // Получаем транзакции
        $transactions = $db->fetchAll(
            "SELECT 
                transaction_id, amount, original_amount, paid_amount, 
                actual_amount_received, remaining_amount, currency, 
                status, description, metadata,
                created_at, paid_at, expires_at
             FROM transactions 
             WHERE {$whereClause}
             ORDER BY created_at DESC 
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
        
        // Парсим metadata
        foreach ($transactions as &$trans) {
            $trans['metadata'] = $trans['metadata'] ? json_decode($trans['metadata'], true) : null;
        }
        
        // Получаем общее количество
        $total = $db->fetch(
            "SELECT COUNT(*) as count FROM transactions WHERE {$whereClause}",
            $params
        );
        
        Response::success([
            'transactions' => $transactions,
            'total' => intval($total['count']),
            'limit' => $limit,
            'offset' => $offset
        ]);
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// GET /api/transaction/{id} - Детали конкретной транзакции
elseif ($requestMethod === 'GET' && $pathParts[0] === 'transaction' && isset($pathParts[1])) {
    try {
        $transactionId = $pathParts[1];
        
        // Получаем транзакцию (только свои!)
        $transaction = $db->fetch(
            "SELECT * FROM transactions 
             WHERE transaction_id = ? AND source_id = ?",
            [$transactionId, $source['source_id']]
        );
        
        if (!$transaction) {
            Response::notFound('Transaction not found');
        }
        
        // Парсим metadata
        $transaction['metadata'] = $transaction['metadata'] ? json_decode($transaction['metadata'], true) : null;
        
        // Получаем историю частичных оплат
        $partialPayments = $db->fetchAll(
            "SELECT amount, commission_percent, commission_amount, 
                    status, is_refunded, paid_at, refunded_at
             FROM partial_payments 
             WHERE transaction_id = ? 
             ORDER BY paid_at ASC",
            [$transaction['id']]
        );
        
        // Получаем логи вебхуков
        $webhookLogs = $db->fetchAll(
            "SELECT event_type, is_success, response_code, created_at
             FROM webhook_logs 
             WHERE transaction_id = ? 
             ORDER BY created_at DESC",
            [$transaction['id']]
        );
        
        Response::success([
            'transaction' => $transaction,
            'partial_payments' => $partialPayments,
            'webhook_logs' => $webhookLogs
        ]);
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// GET /api/exchange-rates - Курсы валют
elseif ($requestMethod === 'GET' && $pathParts[0] === 'exchange-rates') {
    try {
        $rates = $db->fetchAll(
            "SELECT from_currency, to_currency, rate, updated_at
             FROM exchange_rates 
             ORDER BY from_currency ASC, to_currency ASC"
        );
        
        Response::success($rates);
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// GET /api/terminals - Статусы терминалов
elseif ($requestMethod === 'GET' && $pathParts[0] === 'terminals') {
    try {
        $terminals = $db->fetchAll(
            "SELECT id, name, status, is_busy, is_active, last_check, last_used
             FROM kaspi_terminals 
             WHERE is_active = 1
             ORDER BY id ASC"
        );
        
        Response::success($terminals);
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// GET /api/statistics - Статистика оплат
elseif ($requestMethod === 'GET' && $pathParts[0] === 'statistics') {
    try {
        // Фильтры
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        // Общая статистика
        $stats = $db->fetch("
            SELECT 
                COUNT(*) as total_transactions,
                COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
                COUNT(CASE WHEN status = 'partially_paid' THEN 1 END) as partially_paid_count,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count,
                SUM(CASE WHEN status = 'paid' THEN paid_amount ELSE 0 END) as total_paid_amount,
                SUM(CASE WHEN status = 'paid' THEN actual_amount_received ELSE 0 END) as total_received_amount
            FROM transactions 
            WHERE source_id = ?
            AND DATE(created_at) BETWEEN ? AND ?
        ", [$source['source_id'], $dateFrom, $dateTo]);
        
        // Статистика по способам оплаты
        $paymentMethodStats = $db->fetchAll("
            SELECT 
                pm.name as payment_method,
                COUNT(*) as count,
                SUM(pp.amount) as total_amount,
                SUM(pp.commission_amount) as total_commission
            FROM partial_payments pp
            JOIN payment_methods pm ON pp.payment_method_id = pm.id
            JOIN transactions t ON pp.transaction_id = t.id
            WHERE t.source_id = ?
            AND DATE(pp.paid_at) BETWEEN ? AND ?
            AND pp.is_refunded = 0
            GROUP BY pm.id
            ORDER BY count DESC
        ", [$source['source_id'], $dateFrom, $dateTo]);
        
        // Кредит и рассрочка отдельно
        $creditStats = $db->fetch("
            SELECT 
                COUNT(*) as count,
                SUM(pp.amount) as total_amount,
                SUM(pp.commission_amount) as total_commission
            FROM partial_payments pp
            JOIN payment_methods pm ON pp.payment_method_id = pm.id
            JOIN transactions t ON pp.transaction_id = t.id
            WHERE t.source_id = ?
            AND DATE(pp.paid_at) BETWEEN ? AND ?
            AND pp.is_refunded = 0
            AND (pm.has_credit = 1 OR pm.has_installment = 1)
        ", [$source['source_id'], $dateFrom, $dateTo]);
        
        // Статистика возвратов
        $refundStats = $db->fetch("
            SELECT 
                COUNT(*) as refund_count,
                SUM(amount) as refunded_amount
            FROM partial_payments
            WHERE is_refunded = 1
            AND transaction_id IN (
                SELECT id FROM transactions WHERE source_id = ?
            )
            AND DATE(refunded_at) BETWEEN ? AND ?
        ", [$source['source_id'], $dateFrom, $dateTo]);
        
        // Общая комиссия
        $totalCommission = floatval($stats['total_paid_amount'] ?? 0) - floatval($stats['total_received_amount'] ?? 0);
        
        Response::success([
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'summary' => [
                'total_transactions' => intval($stats['total_transactions'] ?? 0),
                'paid_count' => intval($stats['paid_count'] ?? 0),
                'partially_paid_count' => intval($stats['partially_paid_count'] ?? 0),
                'cancelled_count' => intval($stats['cancelled_count'] ?? 0),
                'total_paid_amount' => floatval($stats['total_paid_amount'] ?? 0),
                'total_received_amount' => floatval($stats['total_received_amount'] ?? 0),
                'total_commission' => $totalCommission,
                'refund_count' => intval($refundStats['refund_count'] ?? 0),
                'refunded_amount' => floatval($refundStats['refunded_amount'] ?? 0)
            ],
            'by_payment_method' => $paymentMethodStats,
            'credit_installment' => [
                'count' => intval($creditStats['count'] ?? 0),
                'total_amount' => floatval($creditStats['total_amount'] ?? 0),
                'total_commission' => floatval($creditStats['total_commission'] ?? 0)
            ]
        ]);
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

else {
    Response::notFound('API endpoint not found');
}

