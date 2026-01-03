<?php
/**
 * Countries Public Endpoints
 */

// GET /api/countries - Получение активных стран (публичный эндпоинт)
// GET /api/countries/active - Алиас для совместимости
if ($requestMethod === 'GET' && $pathParts[0] === 'countries' && (!isset($pathParts[1]) || $pathParts[1] === 'active')) {
    try {
        $db = Database::getInstance();
        $countries = $db->fetchAll(
            "SELECT id, code, name, name_en, currency_code, currency_symbol, 
                    phone_code, phone_mask, flag_emoji, is_active
             FROM countries 
             WHERE is_active = 1 
             ORDER BY sort_order ASC, id ASC"
        );
        Response::success($countries);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

// GET /api/countries/exchange-rates - Получение курсов валют (публичный)
elseif ($requestMethod === 'GET' && $pathParts[0] === 'countries' && ($pathParts[1] ?? '') === 'exchange-rates') {
    try {
        require_once __DIR__ . '/../includes/ExchangeRates.php';
        $exchangeRates = new ExchangeRates();
        $rates = $exchangeRates->getAllRates();
        Response::success($rates);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

else {
    Response::notFound('Countries endpoint not found');
}

