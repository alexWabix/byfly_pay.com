<?php
/**
 * Cron: Cleanup expired payments
 * Удаляет истекшие неоплаченные транзакции
 * Запускается 1 раз в сутки
 * 
 * ВАЖНО: Частично оплаченные транзакции НЕ удаляются!
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Webhook.php';

$db = Database::getInstance();
$webhook = new Webhook();

echo "=== CLEANUP EXPIRED PAYMENTS ===\n";
echo "[" . date('Y-m-d H:i:s') . "] Starting cleanup...\n\n";

// Находим истекшие транзакции для удаления
// Условия:
// 1. Статус: pending, processing, или failed
// 2. NOT partially_paid (частично оплаченные СОХРАНЯЕМ!)
// 3. Создана > 24 часов назад
// 4. OR expires_at < NOW

$expiredTransactions = $db->fetchAll("
    SELECT * FROM transactions 
    WHERE status IN ('pending', 'processing', 'failed')
    AND status != 'partially_paid'
    AND (
        created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        OR (expires_at IS NOT NULL AND expires_at < NOW())
    )
    ORDER BY created_at ASC
");

$totalFound = count($expiredTransactions);
echo "Found {$totalFound} expired transaction(s) to clean up\n\n";

if ($totalFound === 0) {
    echo "[" . date('Y-m-d H:i:s') . "] Nothing to clean. Done.\n";
    exit(0);
}

$deletedCount = 0;
$webhookSentCount = 0;
$errors = 0;

foreach ($expiredTransactions as $trans) {
    try {
        echo "Processing transaction: {$trans['transaction_id']}\n";
        echo "  Status: {$trans['status']}\n";
        echo "  Created: {$trans['created_at']}\n";
        echo "  Expires: " . ($trans['expires_at'] ?? 'No expiration') . "\n";
        
        // Отправляем вебхук об истечении (если есть webhook_url)
        if (!empty($trans['webhook_url'])) {
            echo "  Sending expiration webhook...\n";
            
            $webhookSent = $webhook->send(
                $trans['id'],
                'expired',
                [
                    'reason' => 'Payment link expired',
                    'expired_at' => date('Y-m-d H:i:s')
                ]
            );
            
            if ($webhookSent) {
                echo "  ✅ Webhook sent\n";
                $webhookSentCount++;
            } else {
                echo "  ⚠️ Webhook failed (will be deleted anyway)\n";
            }
        }
        
        // Удаляем транзакцию
        // Благодаря ON DELETE CASCADE также удалятся:
        // - partial_payments
        // - webhook_logs
        // - transaction_logs (если есть FK)
        
        $deleted = $db->delete('transactions', 'id = ?', [$trans['id']]);
        
        if ($deleted) {
            echo "  ✅ Transaction deleted\n";
            $deletedCount++;
        } else {
            echo "  ❌ Failed to delete\n";
            $errors++;
        }
        
        echo "\n";
        
    } catch (Exception $e) {
        echo "  ❌ Error: {$e->getMessage()}\n\n";
        $errors++;
    }
}

echo "=== CLEANUP SUMMARY ===\n";
echo "Total found: {$totalFound}\n";
echo "Deleted: {$deletedCount}\n";
echo "Webhooks sent: {$webhookSentCount}\n";
echo "Errors: {$errors}\n";
echo "\n[" . date('Y-m-d H:i:s') . "] Cleanup completed\n";

