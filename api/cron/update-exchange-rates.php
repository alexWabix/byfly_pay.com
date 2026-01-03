<?php
/**
 * Cron job: Update exchange rates from National Bank of Kazakhstan
 * Schedule: Every minute
 * Crontab: (asterisk)/1 (asterisk) (asterisk) (asterisk) (asterisk) php /path/to/api/cron/update-exchange-rates.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ExchangeRates.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting exchange rates update...\n";

try {
    $exchangeRates = new ExchangeRates();
    $result = $exchangeRates->updateRates();
    
    if ($result) {
        echo "✅ Exchange rates updated successfully\n";
    } else {
        echo "⚠️ Failed to update exchange rates (check logs)\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    error_log("Exchange rates cron error: " . $e->getMessage());
}

echo "[" . date('Y-m-d H:i:s') . "] Finished\n";

