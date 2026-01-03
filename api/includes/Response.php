<?php
/**
 * JSON Response Helper Class
 */

class Response {
    
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    public static function error($message, $statusCode = 400, $errors = null) {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        self::json($response, $statusCode);
    }

    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }

    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }

    public static function notFound($message = 'Not found') {
        self::error($message, 404);
    }

    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }
}
