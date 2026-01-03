<?php
/**
 * Test script to verify API setup
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'success' => true,
    'message' => 'API is working!',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'extensions' => [
        'pdo' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'curl' => extension_loaded('curl'),
        'json' => extension_loaded('json')
    ]
], JSON_PRETTY_PRINT);

