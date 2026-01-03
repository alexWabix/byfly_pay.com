<?php
/**
 * ByFly Travel Payment Center - Configuration
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'payments');
define('DB_USER', 'payments');
define('DB_PASS', '2350298aweA');
define('DB_CHARSET', 'utf8mb4');

// SMS Service Configuration (smsc.kz)
define('SMS_LOGIN', 'Byfly2024');
define('SMS_PASSWORD', '2350298aweA!@');
define('SMS_SENDER', 'ByFly');
define('SMS_CODE_LENGTH', 6);
define('SMS_CODE_EXPIRE_MINUTES', 10);

// JWT Configuration
define('JWT_SECRET', 'byfly_payment_center_secret_key_2024_' . md5(DB_PASS));
define('JWT_EXPIRE_HOURS', 24);

// API Configuration
define('API_TOKEN_LENGTH', 64);
define('TRANSACTION_ID_LENGTH', 32);

// Kaspi Configuration
define('KASPI_QR_TIMEOUT', 10); // seconds
define('KASPI_PAYMENT_TIMEOUT', 300); // 5 minutes
define('KASPI_STATUS_CHECK_INTERVAL', 1); // second

// CORS Configuration
define('CORS_ALLOWED_ORIGINS', '*');

// Timezone
date_default_timezone_set('Asia/Almaty');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Create logs directory if not exists
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

