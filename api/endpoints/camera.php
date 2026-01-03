<?php
/**
 * Camera Proxy Endpoint
 * Проксирует изображения с камер для обхода CORS
 */

// Проверка JWT токена
$token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
$auth = new Auth();
$admin = $auth->validateToken($token);

if (!$admin) {
    Response::unauthorized('Требуется авторизация');
}

// GET /api/camera/{camera_id} - Получить изображение с камеры
if ($requestMethod === 'GET' && isset($pathParts[1])) {
    $cameraId = intval($pathParts[1]);
    
    if ($cameraId < 1 || $cameraId > 10) {
        Response::error('Неверный ID камеры', 400);
    }
    
    try {
        $cameraUrl = "http://109.175.215.40:3000/capture/{$cameraId}";
        
        // Получаем изображение
        $ch = curl_init($cameraUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$imageData) {
            error_log("Camera proxy error: HTTP $httpCode, Error: $curlError");
            Response::error("Не удалось получить изображение с камеры (HTTP $httpCode)", 500);
        }
        
        // Отдаем изображение с правильными заголовками
        header('Content-Type: image/bmp');
        header('Content-Length: ' . strlen($imageData));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Access-Control-Allow-Origin: *');
        
        echo $imageData;
        exit;
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
}

else {
    Response::notFound('Camera endpoint not found');
}




