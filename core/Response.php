<?php
/**
 * Response
 * Small helper for the REST API layer (api/) so every endpoint returns
 * JSON in the same shape. Not used by the server-rendered web pages.
 */
class Response
{
    public static function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success($data = null, string $message = 'OK'): void
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], 200);
    }

    public static function error(string $message, int $code = 400): void
    {
        self::json(['success' => false, 'message' => $message, 'data' => null], $code);
    }
}
