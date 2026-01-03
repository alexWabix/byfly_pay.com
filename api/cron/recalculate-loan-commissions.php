<?php
/**
 * Пересчет комиссий для транзакций с Loan/Credit/Installment
 * Запустите один раз для исправления уже оплаченных транзакций
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();

echo "=== RECALCULATING LOAN COMMISSIONS ===\n\n";

// Находим все оплаченные транзакции где ProductType = Loan/Credit/Installment
$transactions = $db->fetchAll(
    "SELECT * FROM transactions 
     WHERE status = 'paid' 
     AND terminal_product_type IN ('Loan', 'Credit', 'Installment')
     ORDER BY created_at DESC"
);

echo "Found " . count($transactions) . " transactions with Loan/Credit/Installment\n\n";

foreach ($transactions as $trans) {
    echo "Transaction: {$trans['transaction_id']}\n";
    echo "  Original: {$trans['original_amount']} {$trans['currency']}\n";
    echo "  Paid by client: {$trans['paid_amount']} {$trans['currency']}\n";
    echo "  Product type: {$trans['terminal_product_type']}\n";
    
    $paidAmount = floatval($trans['paid_amount']);
    $originalAmount = floatval($trans['original_amount']);
    
    // Получаем реальный процент комиссии из способа оплаты
    $commissionPercent = 14.00; // По умолчанию
    if ($trans['payment_method_id']) {
        $paymentMethod = $db->fetch(
            "SELECT credit_commission_percent, installment_commission_percent 
             FROM payment_methods 
             WHERE id = ? LIMIT 1",
            [$trans['payment_method_id']]
        );
        
        if ($paymentMethod) {
            $commissionPercent = floatval($paymentMethod['installment_commission_percent'] ?? $paymentMethod['credit_commission_percent'] ?? 14.00);
        }
    }
    
    // Kaspi удерживает процент при Loan/Credit/Installment
    $bankCommission = round($paidAmount * ($commissionPercent / 100), 2);
    $merchantReceives = $paidAmount - $bankCommission;
    $shortage = $originalAmount - $merchantReceives;
    
    echo "  Bank commission ({$commissionPercent}%): $bankCommission {$trans['currency']}\n";
    echo "  Merchant receives: $merchantReceives {$trans['currency']}\n";
    echo "  Shortage: $shortage {$trans['currency']}\n";
    
    if ($shortage > 10) {
        echo "  ⚠️ NEEDS ADDITIONAL PAYMENT!\n";
        
        // Обновляем транзакцию
        $db->update(
            'transactions',
            [
                'status' => 'partially_paid',
                'actual_amount_received' => $merchantReceives,
                'commission_amount' => $bankCommission,
                'remaining_amount' => $shortage,
                'needs_additional_payment' => 1,
                'payment_mismatch' => 1
            ],
            'id = ?',
            [$trans['id']]
        );
        
        echo "  ✅ Updated to partially_paid, needs $shortage {$trans['currency']} more\n";
    } else {
        echo "  ✅ OK, no shortage\n";
    }
    
    echo "\n";
}

echo "Done!\n";

