<?php
/**
 * Authentication Endpoints
 */

$auth = new Auth();

// POST /api/auth/send-code - Отправка SMS кода
if ($requestMethod === 'POST' && $pathParts[1] === 'send-code') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['phone'])) {
        Response::error('Номер телефона обязателен', 400);
    }

    try {
        $auth->sendSmsCode($data['phone']);
        Response::success(null, 'SMS код отправлен');
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// POST /api/auth/verify - Проверка кода и получение токена
elseif ($requestMethod === 'POST' && $pathParts[1] === 'verify') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['phone']) || !isset($data['code'])) {
        Response::error('Номер телефона и код обязательны', 400);
    }

    try {
        $result = $auth->verifyCode($data['phone'], $data['code']);
        Response::success($result, 'Авторизация успешна');
    } catch (Exception $e) {
        Response::error($e->getMessage(), 401);
    }
}

// POST /api/auth/logout - Выход
elseif ($requestMethod === 'POST' && $pathParts[1] === 'logout') {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');

    if ($token) {
        $db = Database::getInstance();
        $db->delete('admin_tokens', 'token = ?', [$token]);
    }

    Response::success(null, 'Выход выполнен');
}

else {
    Response::notFound('Auth endpoint not found');
}
