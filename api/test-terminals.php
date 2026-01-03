<?php
/**
 * Test script to check terminal connectivity
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== ТЕСТ ПОДКЛЮЧЕНИЯ К ТЕРМИНАЛАМ KASPI ===\n\n";

$terminals = [
    ['ip' => 'http://109.175.215.40', 'port' => 130, 'camera' => 4],
    ['ip' => 'http://109.175.215.40', 'port' => 132, 'camera' => 8],
    ['ip' => 'http://188.127.40.70', 'port' => 130, 'camera' => 4],
];

foreach ($terminals as $terminal) {
    $url = "{$terminal['ip']}:{$terminal['port']}/v2/deviceinfo";
    $cameraUrl = "http://109.175.215.40:3000/capture/{$terminal['camera']}";
    
    echo "Терминал: {$terminal['ip']}:{$terminal['port']}\n";
    echo "URL: $url\n";
    
    // Тест терминала
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $start = microtime(true);
    $response = curl_exec($ch);
    $duration = round((microtime(true) - $start) * 1000);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ ДОСТУПЕН (HTTP $httpCode, {$duration}ms)\n";
        echo "   Response: " . substr($response, 0, 200) . "...\n";
    } elseif ($httpCode === 0) {
        echo "❌ НЕ ДОСТУПЕН - Нет соединения!\n";
        echo "   Ошибка: $curlError\n";
        echo "   Возможные причины:\n";
        echo "   - Firewall блокирует исходящие соединения\n";
        echo "   - Терминалы в другой сети\n";
        echo "   - IP адрес недоступен с этого сервера\n";
    } else {
        echo "⚠️  Код: $httpCode\n";
        echo "   Ошибка: $curlError\n";
    }
    
    // Тест камеры
    echo "Камера: $cameraUrl\n";
    $ch = curl_init($cameraUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ Камера доступна\n";
    } else {
        echo "❌ Камера недоступна (HTTP $httpCode)\n";
    }
    
    echo "\n" . str_repeat('-', 60) . "\n\n";
}

echo "\n=== ДОПОЛНИТЕЛЬНАЯ ДИАГНОСТИКА ===\n\n";

// Проверка разрешений PHP
echo "PHP allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'Включено ✅' : 'Отключено ❌') . "\n";
echo "PHP disable_functions: " . (ini_get('disable_functions') ?: 'Нет ограничений ✅') . "\n";

// Проверка curl
if (function_exists('curl_version')) {
    $version = curl_version();
    echo "cURL версия: {$version['version']} ✅\n";
} else {
    echo "cURL: Не установлен ❌\n";
}

// Проверка исходящих соединений
echo "\nПроверка исходящих соединений:\n";
$testUrls = [
    'https://google.com',
    'http://109.175.215.40:3000',
];

foreach ($testUrls as $testUrl) {
    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "$testUrl - " . ($httpCode > 0 ? "✅ OK ($httpCode)" : "❌ Недоступен") . "\n";
}

echo "\n=== РЕКОМЕНДАЦИИ ===\n\n";
echo "Если терминалы недоступны:\n";
echo "1. Проверьте firewall на сервере\n";
echo "2. Убедитесь что IP 109.175.215.40 и 188.127.40.70 доступны\n";
echo "3. Проверьте что порты 130-140 открыты\n";
echo "4. Возможно нужно добавить IP сервера в whitelist терминалов\n";
echo "5. Попробуйте: curl http://109.175.215.40:130/v2/deviceinfo\n";


