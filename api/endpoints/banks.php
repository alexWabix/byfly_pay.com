<?php
/**
 * Banks Management Endpoints
 * Управление банковскими счетами
 */

// Проверка JWT токена
$authHeader = null;

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

// GET /api/banks/stats - Общая статистика (должно быть первым!)
if ($requestMethod === 'GET' && isset($pathParts[1]) && $pathParts[1] === 'stats') {
    try {
        // Общая статистика по всем банкам
        $stats = $db->fetchAll(
            "SELECT 
                country_name,
                currency,
                COUNT(*) as banks_count,
                SUM(balance) as total_balance,
                SUM(CASE WHEN is_active = 1 THEN balance ELSE 0 END) as active_balance
             FROM banks
             GROUP BY country_name, currency
             ORDER BY country_name, currency"
        );
        
        // Приход/расход за сегодня
        $today = date('Y-m-d');
        $todayStats = $db->fetchAll(
            "SELECT 
                b.currency,
                SUM(CASE WHEN bt.type = 'income' THEN bt.amount ELSE 0 END) as income_today,
                SUM(CASE WHEN bt.type = 'expense' THEN bt.amount ELSE 0 END) as expense_today,
                COUNT(*) as transactions_today
             FROM bank_transactions bt
             JOIN banks b ON bt.bank_id = b.id
             WHERE DATE(bt.created_at) = ?
             GROUP BY b.currency",
            [$today]
        );
        
        Response::success([
            'stats' => $stats,
            'today' => $todayStats
        ]);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// GET /api/banks - Список всех банков
elseif ($requestMethod === 'GET' && !isset($pathParts[1])) {
    try {
        $banks = $db->fetchAll(
            "SELECT * FROM banks ORDER BY country_name ASC, name ASC"
        );
        Response::success($banks);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// GET /api/banks/{id} - Информация о банке
elseif ($requestMethod === 'GET' && isset($pathParts[1]) && is_numeric($pathParts[1])) {
    try {
        $bankId = $pathParts[1];
        $bank = $db->fetch("SELECT * FROM banks WHERE id = ?", [$bankId]);
        
        if (!$bank) {
            Response::error('Банк не найден', 404);
        }
        
        // Получаем последние транзакции
        $transactions = $db->fetchAll(
            "SELECT bt.*, a.name as admin_name 
             FROM bank_transactions bt
             LEFT JOIN admins a ON bt.created_by_admin_id = a.id
             WHERE bt.bank_id = ?
             ORDER BY bt.created_at DESC
             LIMIT 50",
            [$bankId]
        );
        
        $bank['transactions'] = $transactions;
        
        Response::success($bank);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/banks/{id}/transaction - Добавить транзакцию (приход/расход)
elseif ($requestMethod === 'POST' && isset($pathParts[1]) && isset($pathParts[2]) && $pathParts[2] === 'transaction') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $bankId = $pathParts[1];
        
        // Получаем текущий баланс
        $bank = $db->fetch("SELECT * FROM banks WHERE id = ?", [$bankId]);
        if (!$bank) {
            Response::error('Банк не найден', 404);
        }
        
        $amount = floatval($data['amount']);
        $type = $data['type']; // income или expense
        
        // Вычисляем новый баланс
        $balanceBefore = floatval($bank['balance']);
        $balanceAfter = $type === 'income' ? ($balanceBefore + $amount) : ($balanceBefore - $amount);
        
        // Создаем транзакцию
        $db->insert('bank_transactions', [
            'bank_id' => $bankId,
            'payment_id' => $data['payment_id'] ?? null,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $data['description'] ?? null,
            'reference' => $data['reference'] ?? null,
            'created_by_admin_id' => $admin['admin_id']
        ]);
        
        // Обновляем баланс банка
        $db->update('banks', ['balance' => $balanceAfter], 'id = ?', [$bankId]);
        
        Response::success(['new_balance' => $balanceAfter], 'Транзакция добавлена', 201);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/banks - Создать банк
elseif ($requestMethod === 'POST' && !isset($pathParts[1])) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $id = $db->insert('banks', [
            'name' => $data['name'],
            'account_number' => $data['account_number'] ?? null,
            'country_code' => $data['country_code'] ?? 'KZ',
            'country_name' => $data['country_name'] ?? 'Казахстан',
            'currency' => $data['currency'] ?? 'KZT',
            'balance' => $data['initial_balance'] ?? 0,
            'initial_balance' => $data['initial_balance'] ?? 0,
            'bank_color' => $data['bank_color'] ?? '#667eea',
            'bank_icon' => $data['bank_icon'] ?? '🏦',
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ]);
        
        // Создаем начальную транзакцию если есть начальный баланс
        if (($data['initial_balance'] ?? 0) > 0) {
            $db->insert('bank_transactions', [
                'bank_id' => $id,
                'type' => 'adjustment',
                'amount' => $data['initial_balance'],
                'balance_before' => 0,
                'balance_after' => $data['initial_balance'],
                'description' => 'Начальный баланс',
                'created_by_admin_id' => $admin['admin_id']
            ]);
        }
        
        Response::success(['id' => $id], 'Банк создан', 201);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// PUT /api/banks/{id} - Обновить банк
elseif ($requestMethod === 'PUT' && isset($pathParts[1]) && is_numeric($pathParts[1])) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $bankId = $pathParts[1];
        
        // Получаем текущий банк
        $bank = $db->fetch("SELECT * FROM banks WHERE id = ?", [$bankId]);
        if (!$bank) {
            Response::error('Банк не найден', 404);
        }
        
        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['account_number'])) $updateData['account_number'] = $data['account_number'];
        if (isset($data['country_code'])) $updateData['country_code'] = $data['country_code'];
        if (isset($data['country_name'])) $updateData['country_name'] = $data['country_name'];
        if (isset($data['currency'])) $updateData['currency'] = $data['currency'];
        if (isset($data['bank_color'])) $updateData['bank_color'] = $data['bank_color'];
        if (isset($data['bank_icon'])) $updateData['bank_icon'] = $data['bank_icon'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];
        
        // Если изменяется баланс - создаем корректировку
        if (isset($data['balance'])) {
            $oldBalance = floatval($bank['balance']);
            $newBalance = floatval($data['balance']);
            
            if ($oldBalance != $newBalance) {
                $diff = $newBalance - $oldBalance;
                
                // Создаем транзакцию корректировки
                $db->insert('bank_transactions', [
                    'bank_id' => $bankId,
                    'type' => 'adjustment',
                    'amount' => abs($diff),
                    'balance_before' => $oldBalance,
                    'balance_after' => $newBalance,
                    'description' => 'Корректировка баланса вручную: ' . ($diff > 0 ? '+' : '') . $diff,
                    'created_by_admin_id' => $admin['admin_id']
                ]);
            }
            
            $updateData['balance'] = $newBalance;
        }
        
        $db->update('banks', $updateData, 'id = ?', [$bankId]);
        
        Response::success(null, 'Банк обновлен');
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// DELETE /api/banks/{id} - Удалить банк
elseif ($requestMethod === 'DELETE' && isset($pathParts[1]) && is_numeric($pathParts[1])) {
    try {
        $bankId = $pathParts[1];
        
        // Проверяем что банк существует
        $bank = $db->fetch("SELECT * FROM banks WHERE id = ?", [$bankId]);
        if (!$bank) {
            Response::error('Банк не найден', 404);
        }
        
        // Проверяем что нет связанных платежей
        $linkedPayments = $db->fetch(
            "SELECT COUNT(*) as count FROM outgoing_payments WHERE bank_id = ?",
            [$bankId]
        );
        
        if ($linkedPayments['count'] > 0) {
            Response::error('Нельзя удалить банк с привязанными платежами. Сначала удалите или перенесите платежи.', 400);
        }
        
        // Удаляем банк (транзакции удалятся автоматически через CASCADE)
        $db->delete('banks', 'id = ?', [$bankId]);
        
        Response::success(null, 'Банк удален');
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/banks - Создать банк (перенесли выше, этот код удаляем)

else {
    Response::notFound('Banks endpoint not found');
}

