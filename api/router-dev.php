<?php
/**
 * Development Router for PHP Built-in Server
 */

// CORS headers для разработки
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Статические файлы
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf)$/', $uri)) {
    return false;
}

// API routes
if (strpos($uri, '/api/') === 0) {
    $_SERVER['REQUEST_URI'] = $uri;
    require_once __DIR__ . '/index.php';
    exit;
}

// Для всех остальных запросов - возвращаем index.html (SPA)
if (file_exists(__DIR__ . '/../dist/index.html')) {
    require_once __DIR__ . '/../dist/index.html';
} else {
    echo "Build frontend first: npm run build";
}

