<?php
declare(strict_types=1);

namespace GI\Core;

class ApiResponse
{
    public static function success(mixed $data = null, int $status = 200, string $message = 'OK'): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ]);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        $response = [
            'status'  => 'error',
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        echo json_encode($response);
    }

    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Not found'): void
    {
        self::error($message, 404);
    }
}
