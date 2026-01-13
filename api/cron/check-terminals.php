<?php
/**
 * CRON Job: Проверка терминалов Kaspi
 * Запуск: каждый час
 * Команда: php /var/www/html/api/cron/check-terminals.php
 */

// Проверяем что скрипт запущен из CLI
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/KaspiTerminal.php';
require_once __DIR__ . '/../includes/Auth.php';

echo "=== KASPI TERMINALS CHECK ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

$db = Database::getInstance();
$kaspiTerminal = new KaspiTerminal();
$auth = new Auth();

// Получаем все активные терминалы
$terminals = $db->fetchAll("SELECT * FROM kaspi_terminals WHERE is_active = 1 ORDER BY id ASC");

echo "Checking " . count($terminals) . " terminals...\n\n";

$problemTerminals = [];
$checkedCount = 0;
$onlineCount = 0;
$offlineCount = 0;

foreach ($terminals as $terminal) {
    $checkedCount++;
    
    echo "[$checkedCount/" . count($terminals) . "] Checking Terminal #{$terminal['id']} ({$terminal['name']})...\n";
    
    try {
        // Проверяем статус терминала
        $status = $kaspiTerminal->checkTerminalStatus($terminal['id']);
        
        if ($status['status'] === 'online') {
            echo "  ✅ ONLINE - " . $status['message'] . "\n";
            $onlineCount++;
            
            // Если был сброшен зависший платеж
            if ($status['stuck_cleared'] ?? false) {
                echo "  ⚠️  Cleared stuck payment (>4 min)\n";
                $problemTerminals[] = [
                    'terminal' => $terminal,
                    'issue' => 'Зависшая операция сброшена (>4 мин)',
                    'status' => 'cleared'
                ];
            }
        } else {
            echo "  ❌ OFFLINE - " . $status['message'] . "\n";
            $offlineCount++;
            
            $problemTerminals[] = [
                'terminal' => $terminal,
                'issue' => $status['message'],
                'status' => 'offline'
            ];
        }
        
    } catch (Exception $e) {
        echo "  ❌ ERROR - " . $e->getMessage() . "\n";
        $offlineCount++;
        
        $problemTerminals[] = [
            'terminal' => $terminal,
            'issue' => $e->getMessage(),
            'status' => 'error'
        ];
    }
    
    echo "\n";
}

echo "=== SUMMARY ===\n";
echo "Total terminals: " . count($terminals) . "\n";
echo "Online: $onlineCount\n";
echo "Offline: $offlineCount\n";
echo "Problems detected: " . count($problemTerminals) . "\n\n";

// Если есть проблемные терминалы - отправляем SMS админам
if (!empty($problemTerminals)) {
    echo "=== SENDING ALERTS ===\n";
    
    // Получаем всех супер-админов
    $admins = $db->fetchAll("SELECT phone FROM admins WHERE is_super_admin = 1 AND is_active = 1");
    
    foreach ($admins as $admin) {
        $phone = $admin['phone'];
        
        // Формируем сообщение
        $message = "⚠️ Kaspi Terminals Alert\n\n";
        $message .= "Обнаружены проблемы с терминалами:\n\n";
        
        foreach ($problemTerminals as $problem) {
            $terminalName = $problem['terminal']['name'];
            $issue = $problem['issue'];
            $message .= "• {$terminalName}: {$issue}\n";
        }
        
        $message .= "\nВремя: " . date('d.m.Y H:i');
        $message .= "\n\nПроверьте терминалы: https://byfly-pay.com/terminals";
        
        try {
            $auth->sendSms($phone, $message);
            echo "  ✅ SMS sent to $phone\n";
        } catch (Exception $e) {
            echo "  ❌ Failed to send SMS to $phone: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
}

// Логируем результат в БД
try {
    $db->insert('system_logs', [
        'action' => 'terminals_check',
        'status' => $offlineCount > 0 ? 'warning' : 'success',
        'message' => "Checked $checkedCount terminals. Online: $onlineCount, Offline: $offlineCount",
        'data' => json_encode([
            'total' => count($terminals),
            'online' => $onlineCount,
            'offline' => $offlineCount,
            'problems' => $problemTerminals
        ]),
        'created_at' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    // Таблица может не существовать - не критично
}

echo "=== COMPLETED ===\n";
echo "Finished at: " . date('Y-m-d H:i:s') . "\n";

exit(0);





