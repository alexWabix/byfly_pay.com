<?php
/**
 * Approval System Endpoints
 * Система согласования исходящих платежей
 */

$db = Database::getInstance();
$auth = new Auth();

// ПУБЛИЧНЫЕ ENDPOINTS (без авторизации) - проверяются ПЕРВЫМИ

// GET /api/approvals/payment-by-token/{token} - Получение платежа по токену (публичный)
if ($requestMethod === 'GET' && $pathParts[1] === 'payment-by-token' && isset($pathParts[2])) {
    try {
        $token = $pathParts[2];
        
        // Получаем подпись по токену
        $approval = $db->fetch(
            "SELECT pa.*, a.phone, a.name as admin_name
             FROM payment_approvals pa
             JOIN admins a ON pa.admin_id = a.id
             WHERE pa.approval_token = ?",
            [$token]
        );
        
        if (!$approval) {
            Response::error('Неверная или истекшая ссылка', 404);
        }
        
        // Получаем платеж
        $payment = $db->fetch(
            "SELECT op.*, pc.name as category_name, pc.icon_emoji as category_icon,
                    creator.name as created_by_name
             FROM outgoing_payments op
             LEFT JOIN payment_categories pc ON op.category_id = pc.id
             LEFT JOIN admins creator ON op.created_by_admin_id = creator.id
             WHERE op.id = ?",
            [$approval['payment_id']]
        );
        
        if (!$payment) {
            Response::error('Платеж не найден', 404);
        }
        
        Response::success([
            'payment' => $payment,
            'approval' => $approval
        ]);
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
    exit; // Важно! Выходим чтобы не проверять авторизацию
}

// POST /api/approvals/approve-by-token/{token} - Одобрить по токену (публичный)
elseif ($requestMethod === 'POST' && $pathParts[1] === 'approve-by-token' && isset($pathParts[2])) {
    try {
        $token = $pathParts[2];
        
        // Получаем подпись
        $approval = $db->fetch(
            "SELECT * FROM payment_approvals WHERE approval_token = ?",
            [$token]
        );
        
        if (!$approval) {
            Response::error('Неверная или истекшая ссылка', 404);
        }
        
        if ($approval['status'] !== 'pending') {
            Response::error('Этот платеж уже обработан', 400);
        }
        
        // Обновляем статус
        $db->update(
            'payment_approvals',
            [
                'status' => 'approved',
                'approved_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$approval['id']]
        );
        
        // Проверяем все ли одобрили
        $approvedCount = $db->fetch(
            "SELECT COUNT(*) as count FROM payment_approvals 
             WHERE payment_id = ? AND status = 'approved'",
            [$approval['payment_id']]
        )['count'];
        
        $payment = $db->fetch("SELECT * FROM outgoing_payments WHERE id = ?", [$approval['payment_id']]);
        
        if ($approvedCount >= $payment['required_approvals']) {
            $db->update(
                'outgoing_payments',
                [
                    'status' => 'approved',
                    'approved_count' => $approvedCount,
                    'approved_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$approval['payment_id']]
            );
        } else {
            $db->update(
                'outgoing_payments',
                ['approved_count' => $approvedCount],
                'id = ?',
                [$approval['payment_id']]
            );
        }
        
        Response::success(null, 'Платеж успешно одобрен');
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
    exit;
}

// POST /api/approvals/reject-by-token/{token} - Отклонить по токену (публичный)
elseif ($requestMethod === 'POST' && $pathParts[1] === 'reject-by-token' && isset($pathParts[2])) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $token = $pathParts[2];
        
        // Получаем подпись
        $approval = $db->fetch(
            "SELECT * FROM payment_approvals WHERE approval_token = ?",
            [$token]
        );
        
        if (!$approval) {
            Response::error('Неверная или истекшая ссылка', 404);
        }
        
        if ($approval['status'] !== 'pending') {
            Response::error('Этот платеж уже обработан', 400);
        }
        
        // Обновляем статус
        $db->update(
            'payment_approvals',
            [
                'status' => 'rejected',
                'comment' => $data['comment'] ?? null,
                'rejected_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$approval['id']]
        );
        
        // Платеж отклонен
        $db->update(
            'outgoing_payments',
            [
                'status' => 'rejected',
                'rejected_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$approval['payment_id']]
        );
        
        Response::success(null, 'Платеж отклонен');
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
    exit;
}

// ЗАЩИЩЕННЫЕ ENDPOINTS (требуют авторизацию)

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
$admin = $auth->validateToken($token);

if (!$admin) {
    Response::unauthorized('Требуется авторизация');
}

// GET /api/approvals/categories - Список категорий
if ($requestMethod === 'GET' && $pathParts[1] === 'categories') {
    try {
        $categories = $db->fetchAll(
            "SELECT * FROM payment_categories WHERE is_active = 1 ORDER BY name ASC"
        );
        Response::success($categories);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/approvals/categories - Создать категорию
elseif ($requestMethod === 'POST' && $pathParts[1] === 'categories') {
    $data = json_decode(file_get_contents('php://input'), true);
    try {
        $id = $db->insert('payment_categories', $data);
        Response::success(['id' => $id], 'Категория создана', 201);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// GET /api/approvals/payments - Список платежей на согласование
elseif ($requestMethod === 'GET' && $pathParts[1] === 'payments' && !isset($pathParts[2])) {
    try {
        $isCreator = isset($_GET['created_by_me']) && $_GET['created_by_me'] === 'true';
        $isApprover = isset($_GET['pending_approval']) && $_GET['pending_approval'] === 'true';
        
        if ($isCreator) {
            $payments = $db->fetchAll(
                "SELECT op.*, pc.name as category_name, pc.icon_emoji as category_icon, pc.color as category_color,
                        a.name as created_by_name
                 FROM outgoing_payments op
                 LEFT JOIN payment_categories pc ON op.category_id = pc.id
                 LEFT JOIN admins a ON op.created_by_admin_id = a.id
                 WHERE op.created_by_admin_id = ?
                 ORDER BY op.created_at DESC",
                [$admin['admin_id']]
            );
        } elseif ($isApprover) {
            $payments = $db->fetchAll(
                "SELECT op.*, pc.name as category_name, pc.icon_emoji as category_icon, pc.color as category_color,
                        a.name as created_by_name, pa.status as my_status
                 FROM outgoing_payments op
                 JOIN payment_approvals pa ON op.id = pa.payment_id
                 LEFT JOIN payment_categories pc ON op.category_id = pc.id
                 LEFT JOIN admins a ON op.created_by_admin_id = a.id
                 WHERE pa.admin_id = ? AND pa.status = 'pending'
                 ORDER BY op.created_at DESC",
                [$admin['admin_id']]
            );
        } else {
            // Фильтр по статусу
            $statusFilter = '';
            if (isset($_GET['status']) && in_array($_GET['status'], ['pending', 'approved', 'rejected', 'paid', 'cancelled'])) {
                $statusFilter = " WHERE op.status = '" . $_GET['status'] . "'";
            }
            
            // Фильтр по категории
            $categoryFilter = '';
            if (isset($_GET['category_id']) && is_numeric($_GET['category_id'])) {
                $categoryFilter = ($statusFilter ? " AND " : " WHERE ") . "op.category_id = " . intval($_GET['category_id']);
            }
            
            // Добавляем фильтр для скрытия удаленных
            $deletedFilter = ($statusFilter || $categoryFilter) ? " AND " : " WHERE ";
            $deletedFilter .= "op.deleted_at IS NULL";
            
            $payments = $db->fetchAll(
                "SELECT op.*, pc.name as category_name, pc.icon_emoji as category_icon, pc.color as category_color,
                        a.name as created_by_name,
                        CASE WHEN op.created_by_admin_id = ? THEN 1 ELSE 0 END as is_created_by_me
                 FROM outgoing_payments op
                 LEFT JOIN payment_categories pc ON op.category_id = pc.id
                 LEFT JOIN admins a ON op.created_by_admin_id = a.id" .
                $statusFilter . $categoryFilter . $deletedFilter .
                " ORDER BY op.created_at DESC",
                [$admin['admin_id']]
            );
        }
        
        // Добавляем подписи для каждого платежа и определяем my_status
        foreach ($payments as &$payment) {
            $approvals = $db->fetchAll(
                "SELECT pa.*, a.name as admin_name, a.phone as admin_phone
                 FROM payment_approvals pa
                 JOIN admins a ON pa.admin_id = a.id
                 WHERE pa.payment_id = ?
                 ORDER BY pa.approved_at ASC",
                [$payment['id']]
            );
            $payment['approvals'] = $approvals;
            
            // Определяем мой статус подписи
            $payment['my_status'] = null;
            foreach ($approvals as $approval) {
                if ($approval['admin_id'] == $admin['admin_id']) {
                    $payment['my_status'] = $approval['status'];
                    break;
                }
            }
        }
        
        Response::success($payments);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/approvals/payments/{id}/resend-sms - Повторная отправка SMS подписантам
elseif ($requestMethod === 'POST' && $pathParts[1] === 'payments' && isset($pathParts[3]) && $pathParts[3] === 'resend-sms') {
    try {
        $paymentId = $pathParts[2];
        
        // Получаем платеж
        $payment = $db->fetch("SELECT * FROM outgoing_payments WHERE id = ?", [$paymentId]);
        if (!$payment) {
            Response::error('Платеж не найден', 404);
        }
        
        // Получаем всех подписантов со статусом pending
        $pendingApprovals = $db->fetchAll(
            "SELECT pa.*, a.phone, a.name 
             FROM payment_approvals pa
             JOIN admins a ON pa.admin_id = a.id
             WHERE pa.payment_id = ? AND pa.status = 'pending'",
            [$paymentId]
        );
        
        if (empty($pendingApprovals)) {
            Response::error('Нет подписантов ожидающих подписи', 400);
        }
        
        $sent = 0;
        foreach ($pendingApprovals as $approval) {
            if ($approval['phone']) {
                try {
                    $auth->sendApprovalSms(
                        $approval['phone'],
                        $payment['payment_id'],
                        $payment['title'],
                        $payment['amount'],
                        $payment['currency'],
                        $approval['approval_token']
                    );
                    
                    // Обновляем время отправки
                    $db->update(
                        'payment_approvals',
                        [
                            'sms_sent' => 1,
                            'sms_sent_at' => date('Y-m-d H:i:s')
                        ],
                        'id = ?',
                        [$approval['id']]
                    );
                    
                    $sent++;
                } catch (Exception $e) {
                    error_log("Failed to resend SMS: " . $e->getMessage());
                }
            }
        }
        
        Response::success(['sent' => $sent], "SMS отправлено: {$sent} подписантам");
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/approvals/payments - Создать платеж на согласование
elseif ($requestMethod === 'POST' && $pathParts[1] === 'payments' && !isset($pathParts[2])) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $paymentId = bin2hex(random_bytes(16));
        
        $approverIds = $data['approver_ids'] ?? [];
        if (empty($approverIds)) {
            Response::error('Выберите хотя бы одного подписанта', 400);
        }
        
        // Создаем платеж
        $id = $db->insert('outgoing_payments', [
            'payment_id' => $paymentId,
            'category_id' => $data['category_id'] ?? null,
            'created_by_admin_id' => $admin['admin_id'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'KZT',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'recipient' => $data['recipient'] ?? null,
            'document_url' => $data['document_url'] ?? null,
            'document_filename' => $data['document_filename'] ?? null,
            'required_approvals' => count($approverIds)
        ]);
        
        // Создаем записи для подписантов и отправляем SMS
        foreach ($approverIds as $approverId) {
            // Генерируем уникальный токен для подписания
            $approvalToken = Auth::generateApprovalToken();
            
            $db->insert('payment_approvals', [
                'payment_id' => $id,
                'admin_id' => $approverId,
                'status' => 'pending',
                'approval_token' => $approvalToken
            ]);
            
            // Получаем данные подписанта
            $approver = $db->fetch("SELECT * FROM admins WHERE id = ?", [$approverId]);
            
            if ($approver && $approver['phone']) {
                try {
                    // Отправляем SMS с ссылкой на подписание
                    $auth->sendApprovalSms(
                        $approver['phone'],
                        $paymentId,
                        $data['title'],
                        $data['amount'],
                        $data['currency'] ?? 'KZT',
                        $approvalToken
                    );
                    
                    // Отмечаем что SMS отправлено
                    $db->update(
                        'payment_approvals',
                        [
                            'sms_sent' => 1,
                            'sms_sent_at' => date('Y-m-d H:i:s')
                        ],
                        'payment_id = ? AND admin_id = ?',
                        [$id, $approverId]
                    );
                } catch (Exception $e) {
                    // Логируем ошибку но не прерываем создание платежа
                    error_log("Failed to send SMS to approver {$approverId}: " . $e->getMessage());
                }
            }
        }
        
        Response::success(['id' => $id, 'payment_id' => $paymentId], 'Платеж создан и отправлен на согласование', 201);
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/approvals/payments/{id}/approve - Одобрить платеж
elseif ($requestMethod === 'POST' && $pathParts[1] === 'payments' && $pathParts[3] === 'approve') {
    try {
        $paymentId = $pathParts[2];
        
        // Обновляем подпись
        $db->update(
            'payment_approvals',
            [
                'status' => 'approved',
                'approved_at' => date('Y-m-d H:i:s')
            ],
            'payment_id = ? AND admin_id = ?',
            [$paymentId, $admin['admin_id']]
        );
        
        // Пересчитываем количество одобрений
        $approvedCount = $db->fetch(
            "SELECT COUNT(*) as count FROM payment_approvals 
             WHERE payment_id = ? AND status = 'approved'",
            [$paymentId]
        )['count'];
        
        $payment = $db->fetch("SELECT * FROM outgoing_payments WHERE id = ?", [$paymentId]);
        
        // Если все одобрили - меняем статус
        if ($approvedCount >= $payment['required_approvals']) {
            $db->update(
                'outgoing_payments',
                [
                    'status' => 'approved',
                    'approved_count' => $approvedCount,
                    'approved_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$paymentId]
            );
            
            // TODO: Отправить SMS бухгалтеру об одобрении
            
            Response::success(null, 'Платеж полностью одобрен!');
        } else {
            $db->update(
                'outgoing_payments',
                ['approved_count' => $approvedCount],
                'id = ?',
                [$paymentId]
            );
            
            Response::success(null, "Ваша подпись принята. Ожидаем остальных ({$approvedCount}/{$payment['required_approvals']})");
        }
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/approvals/payments/{id}/reject - Отклонить платеж
elseif ($requestMethod === 'POST' && $pathParts[1] === 'payments' && $pathParts[3] === 'reject') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $paymentId = $pathParts[2];
        
        $db->update(
            'payment_approvals',
            [
                'status' => 'rejected',
                'comment' => $data['comment'] ?? null,
                'rejected_at' => date('Y-m-d H:i:s')
            ],
            'payment_id = ? AND admin_id = ?',
            [$paymentId, $admin['admin_id']]
        );
        
        // Платеж отклонен - обновляем статус
        $db->update(
            'outgoing_payments',
            [
                'status' => 'rejected',
                'rejected_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$paymentId]
        );
        
        // TODO: Отправить SMS бухгалтеру об отклонении
        
        Response::success(null, 'Платеж отклонен');
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/approvals/payments/{id}/mark-paid - Отметить как оплаченный
elseif ($requestMethod === 'POST' && $pathParts[1] === 'payments' && $pathParts[3] === 'mark-paid') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $paymentId = $pathParts[2];
        
        $payment = $db->fetch("SELECT * FROM outgoing_payments WHERE id = ?", [$paymentId]);
        
        // Только создатель может отметить как оплаченный
        if ($payment['created_by_admin_id'] != $admin['admin_id']) {
            Response::forbidden('Только создатель платежа может отметить его как оплаченный');
        }
        
        // Платеж должен быть одобрен
        if ($payment['status'] !== 'approved') {
            Response::error('Платеж должен быть сначала одобрен', 400);
        }
        
        $bankId = $data['bank_id'] ?? null;
        
        // Обновляем платеж
        $db->update(
            'outgoing_payments',
            [
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
                'paid_by_admin_id' => $admin['admin_id'],
                'paid_document_url' => $data['document_url'] ?? null,
                'paid_document_filename' => $data['document_filename'] ?? null,
                'bank_id' => $bankId
            ],
            'id = ?',
            [$paymentId]
        );
        
        // Если указан банк - создаем транзакцию расхода
        if ($bankId) {
            $bank = $db->fetch("SELECT * FROM banks WHERE id = ?", [$bankId]);
            
            if ($bank) {
                $balanceBefore = floatval($bank['balance']);
                $amount = floatval($payment['amount']);
                $balanceAfter = $balanceBefore - $amount;
                
                // Создаем транзакцию расхода
                $db->insert('bank_transactions', [
                    'bank_id' => $bankId,
                    'payment_id' => $paymentId,
                    'type' => 'expense',
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'description' => 'Оплата: ' . $payment['title'],
                    'reference' => $payment['payment_id'],
                    'created_by_admin_id' => $admin['admin_id']
                ]);
                
                // Обновляем баланс банка
                $db->update('banks', ['balance' => $balanceAfter], 'id = ?', [$bankId]);
            }
        }
        
        Response::success(null, 'Платеж отмечен как оплаченный');
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/approvals/bulk-approve - Массовое одобрение выбранных
elseif ($requestMethod === 'POST' && $pathParts[1] === 'bulk-approve') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $paymentIds = $data['payment_ids'] ?? [];
        if (empty($paymentIds)) {
            Response::error('Не указаны ID платежей', 400);
        }
        
        $approved = 0;
        $fullyApproved = 0;
        
        foreach ($paymentIds as $paymentId) {
            try {
                // Обновляем подпись
                $db->update(
                    'payment_approvals',
                    [
                        'status' => 'approved',
                        'approved_at' => date('Y-m-d H:i:s')
                    ],
                    'payment_id = ? AND admin_id = ? AND status = \'pending\'',
                    [$paymentId, $admin['admin_id']]
                );
                
                // Пересчитываем количество одобрений
                $approvedCount = $db->fetch(
                    "SELECT COUNT(*) as count FROM payment_approvals 
                     WHERE payment_id = ? AND status = 'approved'",
                    [$paymentId]
                )['count'];
                
                $payment = $db->fetch("SELECT * FROM outgoing_payments WHERE id = ?", [$paymentId]);
                
                // Если все одобрили - меняем статус платежа
                if ($payment && $approvedCount >= $payment['required_approvals']) {
                    $db->update(
                        'outgoing_payments',
                        [
                            'status' => 'approved',
                            'approved_count' => $approvedCount,
                            'approved_at' => date('Y-m-d H:i:s')
                        ],
                        'id = ?',
                        [$paymentId]
                    );
                    $fullyApproved++;
                } else {
                    $db->update(
                        'outgoing_payments',
                        ['approved_count' => $approvedCount],
                        'id = ?',
                        [$paymentId]
                    );
                }
                
                $approved++;
            } catch (Exception $e) {
                error_log("Bulk approve error for payment {$paymentId}: " . $e->getMessage());
                continue;
            }
        }
        
        Response::success([
            'approved' => $approved,
            'fully_approved' => $fullyApproved
        ], "Подписано: {$approved} платежей. Полностью одобрено: {$fullyApproved}");
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/approvals/approve-all-my - Одобрить ВСЕ МОИ платежи ожидающие подписи
elseif ($requestMethod === 'POST' && $pathParts[1] === 'approve-all-my') {
    try {
        // Получаем все платежи где текущий админ должен поставить подпись
        $myPendingApprovals = $db->fetchAll(
            "SELECT payment_id FROM payment_approvals 
             WHERE admin_id = ? AND status = 'pending'",
            [$admin['admin_id']]
        );
        
        if (empty($myPendingApprovals)) {
            Response::success([
                'approved' => 0,
                'fully_approved' => 0
            ], 'Нет платежей ожидающих вашей подписи');
        }
        
        $approved = 0;
        $fullyApproved = 0;
        
        foreach ($myPendingApprovals as $approval) {
            try {
                $paymentId = $approval['payment_id'];
                
                // Обновляем подпись
                $db->update(
                    'payment_approvals',
                    [
                        'status' => 'approved',
                        'approved_at' => date('Y-m-d H:i:s')
                    ],
                    'payment_id = ? AND admin_id = ?',
                    [$paymentId, $admin['admin_id']]
                );
                
                // Пересчитываем количество одобрений
                $approvedCount = $db->fetch(
                    "SELECT COUNT(*) as count FROM payment_approvals 
                     WHERE payment_id = ? AND status = 'approved'",
                    [$paymentId]
                )['count'];
                
                $payment = $db->fetch("SELECT * FROM outgoing_payments WHERE id = ?", [$paymentId]);
                
                // Если все одобрили - меняем статус платежа
                if ($payment && $approvedCount >= $payment['required_approvals']) {
                    $db->update(
                        'outgoing_payments',
                        [
                            'status' => 'approved',
                            'approved_count' => $approvedCount,
                            'approved_at' => date('Y-m-d H:i:s')
                        ],
                        'id = ?',
                        [$paymentId]
                    );
                    $fullyApproved++;
                } else {
                    $db->update(
                        'outgoing_payments',
                        ['approved_count' => $approvedCount],
                        'id = ?',
                        [$paymentId]
                    );
                }
                
                $approved++;
            } catch (Exception $e) {
                error_log("Approve all my error for payment {$paymentId}: " . $e->getMessage());
                continue;
            }
        }
        
        Response::success([
            'approved' => $approved,
            'fully_approved' => $fullyApproved
        ], "✅ Подписано: {$approved} платежей. Полностью одобрено: {$fullyApproved}");
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// GET /api/approvals/payments/{id}/documents - Получить документы платежа
elseif ($requestMethod === 'GET' && $pathParts[1] === 'payments' && $pathParts[3] === 'documents') {
    try {
        $paymentId = $pathParts[2];
        
        $documents = $db->fetchAll(
            "SELECT pd.*, a.name as uploaded_by_name 
             FROM payment_documents pd
             LEFT JOIN admins a ON pd.uploaded_by_admin_id = a.id
             WHERE pd.payment_id = ?
             ORDER BY pd.created_at DESC",
            [$paymentId]
        );
        
        Response::success($documents);
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/approvals/payments/{id}/documents - Добавить документ к платежу
elseif ($requestMethod === 'POST' && $pathParts[1] === 'payments' && $pathParts[3] === 'documents') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $paymentId = $pathParts[2];
        
        // Проверяем что платеж существует и пользователь имеет доступ
        $payment = $db->fetch("SELECT * FROM outgoing_payments WHERE id = ?", [$paymentId]);
        if (!$payment) {
            Response::error('Платеж не найден', 404);
        }
        
        $db->insert('payment_documents', [
            'payment_id' => $paymentId,
            'document_url' => $data['document_url'],
            'document_filename' => $data['document_filename'],
            'document_type' => $data['document_type'] ?? 'additional',
            'uploaded_by_admin_id' => $admin['admin_id'],
            'file_size' => $data['file_size'] ?? null
        ]);
        
        Response::success(null, 'Документ добавлен', 201);
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// DELETE /api/approvals/payments/{id}/documents/{docId} - Удалить документ
elseif ($requestMethod === 'DELETE' && $pathParts[1] === 'payments' && $pathParts[3] === 'documents') {
    try {
        $paymentId = $pathParts[2];
        $docId = $pathParts[4] ?? null;
        
        if (!$docId) {
            Response::error('ID документа не указан', 400);
        }
        
        // Проверяем что документ существует и принадлежит платежу
        $document = $db->fetch(
            "SELECT * FROM payment_documents WHERE id = ? AND payment_id = ?",
            [$docId, $paymentId]
        );
        
        if (!$document) {
            Response::error('Документ не найден', 404);
        }
        
        // Удаляем файл с диска
        $filePath = __DIR__ . '/../../' . $document['document_url'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Удаляем запись из БД
        $db->delete('payment_documents', 'id = ?', [$docId]);
        
        Response::success(null, 'Документ удален');
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// DELETE /api/approvals/payments/{id} - Удалить платеж ПОЛНОСТЬЮ (физическое удаление)
elseif ($requestMethod === 'DELETE' && $pathParts[1] === 'payments' && isset($pathParts[2]) && !isset($pathParts[3])) {
    try {
        $paymentId = $pathParts[2];
        
        // Получаем платеж
        $payment = $db->fetch("SELECT * FROM outgoing_payments WHERE id = ?", [$paymentId]);
        
        if (!$payment) {
            Response::error('Платеж не найден', 404);
        }
        
        // Проверяем что пользователь - создатель или суперадмин
        if ($payment['created_by_admin_id'] != $admin['admin_id'] && !$admin['is_super_admin']) {
            Response::forbidden('Только создатель или суперадмин может удалить платеж');
        }
        
        // Удаляем все связанные документы с диска
        $documents = $db->fetchAll(
            "SELECT * FROM payment_documents WHERE payment_id = ?",
            [$paymentId]
        );
        
        foreach ($documents as $doc) {
            $filePath = __DIR__ . '/../../' . $doc['document_url'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Удаляем основной документ если есть
        if ($payment['document_url']) {
            $filePath = __DIR__ . '/../../' . $payment['document_url'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Удаляем документ подтверждения оплаты если есть
        if ($payment['paid_document_url']) {
            $filePath = __DIR__ . '/../../' . $payment['paid_document_url'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // ПОЛНОЕ физическое удаление из БД
        // Связанные записи удалятся автоматически через ON DELETE CASCADE
        $db->delete('outgoing_payments', 'id = ?', [$paymentId]);
        
        Response::success(null, 'Платеж полностью удален');
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

else {
    Response::notFound('Approval endpoint not found');
}

