<?php
/**
 * ByFly Travel Payment Center - API Entry Point
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Response.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/KaspiTerminal.php';
require_once __DIR__ . '/includes/Transaction.php';
require_once __DIR__ . '/includes/ExchangeRates.php';

// Определяем маршрут
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Удаляем /api/ из начала пути
$path = preg_replace('#^/api/#', '', parse_url($requestUri, PHP_URL_PATH));
$pathParts = array_values(array_filter(explode('/', $path)));

// Маршрутизация
try {
    // Эндпоинты авторизации
    if ($pathParts[0] === 'auth') {
        require_once __DIR__ . '/endpoints/auth.php';
    }
    // Эндпоинты платежей (публичные)
    elseif ($pathParts[0] === 'payment' || $pathParts[0] === 'payment-methods') {
        require_once __DIR__ . '/endpoints/payment.php';
    }
    // Эндпоинты стран (публичные)
    elseif ($pathParts[0] === 'countries') {
        require_once __DIR__ . '/endpoints/countries.php';
    }
    // Эндпоинты загрузки файлов (требуют авторизации)
    elseif ($pathParts[0] === 'upload') {
        require_once __DIR__ . '/endpoints/upload.php';
    }
    // Эндпоинты админки (требуют авторизации)
    elseif ($pathParts[0] === 'admin') {
        require_once __DIR__ . '/endpoints/admin.php';
    }
    // Эндпоинт прокси для камер
    elseif ($pathParts[0] === 'camera') {
        require_once __DIR__ . '/endpoints/camera.php';
    }
    // API Gateway endpoints (расширенное API)
    elseif (in_array($pathParts[0], ['transactions', 'transaction', 'exchange-rates', 'terminals', 'statistics'])) {
        require_once __DIR__ . '/includes/Webhook.php';
        require_once __DIR__ . '/endpoints/api-gateway.php';
    }
    // Approval System endpoints
    elseif ($pathParts[0] === 'approvals') {
        require_once __DIR__ . '/endpoints/approvals.php';
    }
    // Banks Management endpoints
    elseif ($pathParts[0] === 'banks') {
        require_once __DIR__ . '/endpoints/banks.php';
    }
    else {
        Response::notFound('Endpoint not found');
    }

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    Response::serverError($e->getMessage());
}
