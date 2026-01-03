<?php
/**
 * Version Check Endpoint
 * Проверка версии установленных файлов
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$version = [
    'version' => '2.0.1',
    'build_date' => '2025-12-25',
    'files' => [
        'index.php' => [
            'modified' => date('Y-m-d H:i:s', filemtime(__DIR__ . '/index.php')),
            'size' => filesize(__DIR__ . '/index.php'),
            'has_fix' => strpos(file_get_contents(__DIR__ . '/index.php'), 'array_values') !== false ? 'YES' : 'NO'
        ],
        'payment.php' => [
            'modified' => date('Y-m-d H:i:s', filemtime(__DIR__ . '/endpoints/payment.php')),
            'size' => filesize(__DIR__ . '/endpoints/payment.php'),
            'has_public_endpoint' => strpos(file_get_contents(__DIR__ . '/endpoints/payment.php'), 'payment-methods') !== false ? 'YES' : 'NO'
        ],
        'Transaction.php' => [
            'modified' => date('Y-m-d H:i:s', filemtime(__DIR__ . '/includes/Transaction.php')),
            'size' => filesize(__DIR__ . '/includes/Transaction.php'),
            'has_tracking' => strpos(file_get_contents(__DIR__ . '/includes/Transaction.php'), 'trackVisit') !== false ? 'YES' : 'NO'
        ]
    ],
    'status' => 'OK'
];

// Проверяем что все исправления применены
$allFixed = true;
foreach ($version['files'] as $file => $info) {
    foreach ($info as $key => $value) {
        if (strpos($key, 'has_') === 0 && $value === 'NO') {
            $allFixed = false;
            break 2;
        }
    }
}

$version['all_fixes_applied'] = $allFixed;
$version['message'] = $allFixed ? 'Все исправления применены ✅' : 'Файлы не обновлены ❌ Загрузите новый deploy.zip';

echo json_encode($version, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

