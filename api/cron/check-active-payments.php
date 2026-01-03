<?php
/**
 * Cron: Check active payments on terminals
 * Проверяет все активные операции на терминалах ПОСТОЯННО
 * Работает максимум 60 секунд, проверяет каждую секунду
 * Обновляет статусы даже если клиент закрыл страницу
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/KaspiTerminal.php';
require_once __DIR__ . '/../includes/Transaction.php';
require_once __DIR__ . '/../includes/Webhook.php';

// === ЗАЩИТА ОТ ПОВТОРНОГО ЗАПУСКА ===
$lockFile = __DIR__ . '/check-active-payments.lock';
$lockHandle = fopen($lockFile, 'c+');

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Another instance is already running. Exiting.\n";
    exit(0);
}

// Записываем PID в lock-файл
ftruncate($lockHandle, 0);
fwrite($lockHandle, getmypid() . "\n" . date('Y-m-d H:i:s'));
fflush($lockHandle);

// Освобождаем lock при завершении
register_shutdown_function(function() use ($lockHandle, $lockFile) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    @unlink($lockFile);
});

$db = Database::getInstance();
$kaspiTerminal = new KaspiTerminal();
$transaction = new Transaction();
$webhook = new Webhook();

echo "=== CHECK ACTIVE PAYMENTS - CONTINUOUS MODE ===\n";
echo "[" . date('Y-m-d H:i:s') . "] Starting continuous monitoring (max 60 seconds)...\n";

// === ЦИКЛ ПРОВЕРОК (максимум 60 секунд) ===
$startTime = time();
$maxDuration = 60; // Максимум 60 секунд работы
$checkCount = 0;
$lastActiveCount = -1;

while (time() - $startTime < $maxDuration) {
    $checkCount++;
    $currentTime = date('Y-m-d H:i:s');
    
    // Получаем все транзакции в статусе processing
    $activeTransactions = $db->fetchAll(
        "SELECT * FROM transactions 
         WHERE status = 'processing' 
         AND kaspi_terminal_id IS NOT NULL
         ORDER BY created_at ASC"
    );
    
    $activeCount = count($activeTransactions);
    
    // Показываем статус только если количество изменилось
    if ($activeCount !== $lastActiveCount) {
        echo "\n[{$currentTime}] Check #{$checkCount}: Found {$activeCount} active payment(s)\n";
        $lastActiveCount = $activeCount;
    }
    
    // Если нет активных транзакций - выходим
    if ($activeCount === 0) {
        if ($checkCount > 1) {
            echo "[{$currentTime}] No active payments. Exiting after {$checkCount} checks.\n";
        }
        break;
    }
    
    // Проверяем каждую активную транзакцию
    foreach ($activeTransactions as $trans) {
    try {
        echo "  Checking transaction: {$trans['transaction_id']}...\n";
        echo "    Terminal ID: {$trans['kaspi_terminal_id']}\n";
        echo "    Expected amount: {$trans['amount']} {$trans['currency']}\n";
        echo "    Terminal operation ID (processId): " . ($trans['terminal_operation_id'] ?? 'NULL') . "\n";
        echo "    Payment URL: " . ($trans['payment_url'] ?? 'NULL') . "\n";
        echo "    QR Code: " . (empty($trans['qr_code']) ? 'NULL' : 'EXISTS') . "\n";
        
        // ВАЖНО! Проверяем processId перед вызовом
        if (empty($trans['terminal_operation_id'])) {
            echo "    ⚠️ WARNING: terminal_operation_id is NULL! Cannot check status properly.\n";
            echo "    This means initiatePayment didn't save processId to database!\n";
        }
        
        // Проверяем статус на терминале
        $status = $kaspiTerminal->checkPaymentStatus(
            $trans['kaspi_terminal_id'],
            $trans['terminal_operation_id'] ?? $trans['transaction_id']
        );
        
        echo "    Terminal response:\n";
        echo "      " . json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        
        // Если статус unknown - пытаемся актуализировать
        if ($status['status'] === 'unknown' && !empty($trans['terminal_operation_id'])) {
            echo "    Status is unknown, trying to actualize...\n";
            
            try {
                $status = $kaspiTerminal->actualizePaymentStatus(
                    $trans['kaspi_terminal_id'],
                    $trans['terminal_operation_id']
                );
                
                echo "    Actualized response:\n";
                echo "      " . json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
            } catch (Exception $e) {
                echo "    ⚠️ Actualization failed: " . $e->getMessage() . "\n";
            }
        }
        
        if ($status['status'] === 'paid' || $status['status'] === 'success') {
            // Получаем детали оплаты
            // ВАЖНО! amount может быть 0, реальная сумма в chequeInfo.amount
            $actualAmount = $status['amount'] ?? 0;
            
            // Если amount = 0, берем из chequeInfo.amount
            if ($actualAmount == 0 && isset($status['details']['chequeInfo']['amount'])) {
                $amountStr = $status['details']['chequeInfo']['amount'];
                // Парсим строку "505 ₸" → 505
                $actualAmount = floatval(preg_replace('/[^0-9.]/', '', $amountStr));
            }
            
            // Реальный способ оплаты из addInfo.ProductType
            $actualMethod = $status['details']['addInfo']['ProductType'] ?? $status['payment_method'] ?? null;
            
            // Конвертируем ProductType в наш формат
            if ($actualMethod === 'Gold') $actualMethod = 'kaspi_gold';
            elseif ($actualMethod === 'Red') $actualMethod = 'kaspi_red';
            elseif ($actualMethod === 'Installment') $actualMethod = 'kaspi_installment';
            elseif ($actualMethod === 'Credit') $actualMethod = 'kaspi_credit';
            elseif ($actualMethod === 'Loan') {
                // ВАЖНО! Loan - это кредит, может быть комиссия банка!
                $loanTerm = $status['details']['addInfo']['LoanTerm'] ?? 0;
                echo "    ⚠️ LOAN DETECTED! Term: $loanTerm months\n";
                echo "    ⚠️ Bank may take commission from merchant!\n";
                
                // Определяем тип по сроку
                if ($loanTerm == 12) $actualMethod = 'kaspi_installment_12';
                elseif ($loanTerm == 24) $actualMethod = 'kaspi_installment_24';
                else $actualMethod = 'kaspi_credit';
            }
            
            echo "    Parsed actual amount: $actualAmount\n";
            echo "    Expected amount (from DB): {$trans['amount']}\n";
            
            // Определяем был ли несоответствие способа оплаты
            $expectedMethod = $db->fetch(
                "SELECT code FROM payment_methods WHERE id = ?",
                [$trans['payment_method_id']]
            );
            
            $paymentMismatch = ($actualMethod && $expectedMethod && $actualMethod !== $expectedMethod['code']) ? 1 : 0;
            
            // СНАЧАЛА извлекаем детали от терминала
            $terminalOrderNumber = $status['details']['chequeInfo']['orderNumber'] ?? null;
            $terminalTransactionId = $status['transaction_id'] ?? null;
            $terminalChequeStatus = $status['details']['chequeInfo']['status'] ?? null;
            $terminalProductType = $status['details']['addInfo']['ProductType'] ?? null;
            $terminalResponseFull = json_encode($status['details'], JSON_UNESCAPED_UNICODE);
            
            // === ПРАВИЛЬНАЯ ПРОСТАЯ ЛОГИКА ===
            
            // Шаг 1: Извлекаем базовые данные
            $originalAmount = floatval($trans['original_amount']); // Базовая сумма к получению
            $previousNetReceived = floatval($trans['actual_amount_received'] ?? 0); // Уже получено ЧИСТЫМИ
            $previousGrossPaid = floatval($trans['paid_amount'] ?? 0); // Уже оплачено клиентом (всего)
            
            echo "\n=== PAYMENT PROCESSING ===\n";
            echo "Original amount needed: $originalAmount ₸\n";
            echo "Previously received (net): $previousNetReceived ₸\n";
            echo "Current payment (gross): $actualAmount ₸\n";
            
            // Шаг 2: Вычисляем чистую сумму от ТЕКУЩЕЙ оплаты
            $currentNetAmount = $actualAmount;
            $bankCommission = 0;
            $commissionPercent = 0;
            
            // Получаем реальные проценты комиссии из базы данных
            $paymentMethodInfo = $db->fetch(
                "SELECT commission_percent, credit_commission_percent, installment_commission_percent 
                 FROM payment_methods 
                 WHERE code = ? LIMIT 1",
                [$actualMethod]
            );
            
            if ($terminalProductType === 'Loan' || $terminalProductType === 'Credit' || $terminalProductType === 'Installment') {
                // Кредит/Рассрочка: используем процент из БД
                $commissionPercent = $paymentMethodInfo ? floatval($paymentMethodInfo['installment_commission_percent'] ?? $paymentMethodInfo['credit_commission_percent'] ?? 14.00) : 14.00;
                $bankCommission = round($actualAmount * ($commissionPercent / 100), 2);
                $currentNetAmount = $actualAmount - $bankCommission;
                echo "⚠️ Loan/Credit/Installment - bank takes {$commissionPercent}%: $bankCommission ₸\n";
            } elseif ($terminalProductType === 'Gold' || $terminalProductType === 'Red') {
                // Gold/Red: используем процент из БД
                $commissionPercent = $paymentMethodInfo ? floatval($paymentMethodInfo['commission_percent'] ?? 1.00) : 1.00;
                $bankCommission = round($actualAmount * ($commissionPercent / 100), 2);
                $currentNetAmount = $actualAmount - $bankCommission;
                echo "💳 Gold/Red - bank takes {$commissionPercent}%: $bankCommission ₸\n";
            }
            
            echo "Current payment (net): $currentNetAmount ₸\n";
            
            // Шаг 3: Суммируем с предыдущими оплатами
            $totalNetReceived = $previousNetReceived + $currentNetAmount;
            $totalGrossPaid = $previousGrossPaid + $actualAmount;
            
            echo "TOTAL net received: $totalNetReceived ₸\n";
            echo "TOTAL paid by client: $totalGrossPaid ₸\n";
            
            // Шаг 4: Вычисляем остаток
            $remaining = $originalAmount - $totalNetReceived;
            
            echo "Remaining: $remaining ₸\n";
            
            // Шаг 5: Определяем статус
            if ($remaining <= 10) {
                $finalStatus = 'paid';
                $needsAdditionalPayment = 0;
                echo "✅ FULLY PAID!\n";
            } else {
                $finalStatus = 'partially_paid';
                $needsAdditionalPayment = 1;
                echo "⚠️ NEEDS $remaining ₸ MORE\n";
            }
            echo "===================\n\n";
            
            // Terminal details уже извлечены выше
            echo "    Terminal details:\n";
            echo "      Order number: $terminalOrderNumber\n";
            echo "      Transaction ID: $terminalTransactionId\n";
            echo "      Product type: $terminalProductType\n";
            echo "      Cheque status: $terminalChequeStatus\n";
            
            // Записываем частичную оплату в отдельную таблицу (для истории)
            $paymentMethodId = $db->fetch(
                "SELECT id FROM payment_methods WHERE code = ? LIMIT 1",
                [$actualMethod]
            );
            
            $db->insert('partial_payments', [
                'transaction_id' => $trans['id'],
                'payment_method_id' => $paymentMethodId['id'] ?? null,
                'amount' => $actualAmount, // Сколько клиент оплатил
                'commission_percent' => $commissionPercent, // Реальный процент комиссии банка
                'commission_amount' => $bankCommission, // Сумма комиссии банка
                'total_amount' => $actualAmount, // Всего оплачено клиентом
                'status' => 'paid',
                'payment_details' => $terminalResponseFull,
                'paid_at' => date('Y-m-d H:i:s')
            ]);
            
            echo "   ✅ Partial payment record created\n";
            
            // Обновляем транзакцию с правильными данными
            $db->update(
                'transactions',
                [
                    'status' => $finalStatus,
                    'actual_payment_method' => $actualMethod,
                    'actual_amount_received' => $totalNetReceived, // ЧИСТЫМИ (суммарно)
                    'paid_amount' => $totalGrossPaid, // Всего оплачено клиентом
                    'commission_amount' => $bankCommission, // Комиссия от последней оплаты
                    'remaining_amount' => max(0, $remaining),
                    'needs_additional_payment' => $needsAdditionalPayment,
                    'payment_mismatch' => $paymentMismatch,
                    'terminal_order_number' => $terminalOrderNumber,
                    'terminal_transaction_id' => $terminalTransactionId,
                    'terminal_cheque_status' => $terminalChequeStatus,
                    'terminal_product_type' => $terminalProductType,
                    'terminal_response_full' => $terminalResponseFull,
                    'paid_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$trans['id']]
            );
            
            // Освобождаем терминал
            $kaspiTerminal->unlockTerminal($trans['kaspi_terminal_id']);
            
            echo "\n✅ Transaction {$trans['transaction_id']}: " . strtoupper($finalStatus) . "\n";
            echo "   Total received: $totalNetReceived / $originalAmount ₸\n";
            
            if ($paymentMismatch) {
                echo "   ⚠️ Method mismatch! Expected: {$expectedMethod['code']}, Got: {$actualMethod}\n";
            }
            
            if ($needsAdditionalPayment) {
                echo "   ⚠️ Needs $remaining ₸ more\n";
            }
            
            // Отправляем вебхук о статусе платежа
            if (!empty($trans['webhook_url'])) {
                echo "   📤 Sending webhook: {$finalStatus}...\n";
                
                $webhookData = [
                    'payment_method' => $actualMethod,
                    'payment_mismatch' => $paymentMismatch ? true : false
                ];
                
                $webhookSent = $webhook->send($trans['id'], $finalStatus, $webhookData);
                echo "   " . ($webhookSent ? "✅ Webhook sent" : "⚠️ Webhook failed") . "\n";
            }
            
        } elseif ($status['status'] === 'failed' || $status['status'] === 'error' || $status['status'] === 'fail') {
            // Клиент отменил оплату или произошла ошибка
            echo "  ⚠️ Transaction {$trans['transaction_id']}: Payment cancelled or failed\n";
            
            // Возвращаем транзакцию в статус pending (клиент может попробовать снова)
            $db->update(
                'transactions',
                [
                    'status' => 'pending',
                    'kaspi_terminal_id' => null, // Освобождаем привязку к терминалу
                    'terminal_operation_id' => null,
                    'qr_code' => null,
                    'payment_url' => null
                ],
                'id = ?',
                [$trans['id']]
            );
            
            // Освобождаем терминал
            $kaspiTerminal->unlockTerminal($trans['kaspi_terminal_id']);
            
            echo "  🔄 Transaction reset to pending. Terminal freed. Client can try again.\n";
            
            // Отправляем вебхук об отмене
            if (!empty($trans['webhook_url'])) {
                echo "  📤 Sending cancellation webhook...\n";
                
                $webhookSent = $webhook->send($trans['id'], Webhook::EVENT_CANCELLED, [
                    'reason' => 'Client cancelled payment on terminal',
                    'sub_status' => $status['details']['subStatus'] ?? null
                ]);
                
                echo "  " . ($webhookSent ? "✅ Webhook sent" : "⚠️ Webhook failed") . "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "  ⚠️ Error checking transaction {$trans['transaction_id']}: {$e->getMessage()}\n";
        
        // При ошибке проверки освобождаем терминал если прошло > 5 минут
        $createdTime = strtotime($trans['created_at']);
        if (time() - $createdTime > 300) { // 5 минут
            echo "  🔓 Transaction timeout (5+ min). Freeing terminal...\n";
            try {
                $kaspiTerminal->unlockTerminal($trans['kaspi_terminal_id']);
            } catch (Exception $e2) {
                echo "  ⚠️ Failed to unlock terminal: {$e2->getMessage()}\n";
            }
        }
    }
    }
    
    // Задержка 1 секунда перед следующей проверкой (если есть активные транзакции)
    if ($activeCount > 0 && time() - $startTime < $maxDuration) {
        sleep(1);
    }
}

$endTime = time();
$duration = $endTime - $startTime;
echo "\n[" . date('Y-m-d H:i:s') . "] Completed after {$duration} seconds ({$checkCount} checks)\n";

