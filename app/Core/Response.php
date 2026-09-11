<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);

        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Platform, X-Device-Name');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        exit;
    }

    public static function success(string $message = '', array $data = [], int $status = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message = '', int $status = 400, array $errors = []): void
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'data' => null,
        ];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        self::json($payload, $status);
    }
}