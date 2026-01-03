<?php
/**
 * Simple JWT Implementation
 */

class JWT {
    
    public static function encode($payload, $secret) {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('SHA256', "$headerEncoded.$payloadEncoded", $secret, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    public static function decode($jwt, $secret) {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

        $signature = self::base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac('SHA256', "$headerEncoded.$payloadEncoded", $secret, true);

        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception('Invalid token signature');
        }

        $payload = json_decode(self::base64UrlDecode($payloadEncoded));

        if (isset($payload->exp) && $payload->exp < time()) {
            throw new Exception('Token expired');
        }

        return $payload;
    }

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

