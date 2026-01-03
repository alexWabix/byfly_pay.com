<?php
/**
 * Upload Endpoints
 */

// Проверка авторизации - читаем из разных источников
$authHeader = null;

// Пытаемся получить Authorization header из разных мест
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    }
}

$token = str_replace('Bearer ', '', $authHeader ?? '');

$auth = new Auth();
$admin = $auth->validateToken($token);

if (!$admin) {
    error_log("Upload: Auth failed. Token: " . substr($token, 0, 20) . "...");
    Response::unauthorized('Требуется авторизация');
}

// POST /api/upload/icon - Загрузка иконки
if ($requestMethod === 'POST' && $pathParts[1] === 'icon') {
    try {
        if (!isset($_FILES['icon']) || $_FILES['icon']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Файл не загружен', 400);
        }

        $file = $_FILES['icon'];
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
        
        if (!in_array($file['type'], $allowedTypes)) {
            Response::error('Разрешены только PNG, JPG, SVG файлы', 400);
        }

        // Максимальный размер 2MB
        if ($file['size'] > 2 * 1024 * 1024) {
            Response::error('Файл слишком большой (макс 2MB)', 400);
        }

        // Создаем директорию если не существует
        $uploadDir = __DIR__ . '/../../uploads/icons/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Генерируем уникальное имя файла
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('icon_', true) . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Перемещаем файл
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            Response::error('Ошибка сохранения файла', 500);
        }

        // Возвращаем URL
        $url = '/uploads/icons/' . $filename;
        
        Response::success([
            'url' => $url,
            'filename' => $filename
        ], 'Файл загружен');

    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/upload/document - Загрузка документа платежа
elseif ($requestMethod === 'POST' && $pathParts[1] === 'document') {
    try {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Файл не загружен', 400);
        }

        $file = $_FILES['file'];
        $allowedTypes = [
            'application/pdf',
            'image/png', 
            'image/jpeg', 
            'image/jpg',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        if (!in_array($file['type'], $allowedTypes)) {
            Response::error('Разрешены только PDF, JPG, PNG, DOC, DOCX, XLS, XLSX файлы', 400);
        }

        // Максимальный размер 10MB
        if ($file['size'] > 10 * 1024 * 1024) {
            Response::error('Файл слишком большой (макс 10MB)', 400);
        }

        // Создаем директорию если не существует
        $uploadDir = __DIR__ . '/../../uploads/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Генерируем уникальное имя файла
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('doc_', true) . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Перемещаем файл
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            Response::error('Ошибка сохранения файла', 500);
        }

        // Возвращаем URL
        $url = '/uploads/documents/' . $filename;
        
        Response::success([
            'url' => $url,
            'filename' => $file['name'], // Оригинальное имя
            'size' => $file['size']
        ], 'Документ загружен');

    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

else {
    Response::notFound('Upload endpoint not found');
}

