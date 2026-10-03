<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response helpers. Controllers return strings, arrays (-> JSON)
 * or one of the static helpers below.
 */
final class Response
{
    /** @param array<string, string> $headers */
    public static function with(int $status, string $contentType, string $body, array $headers = []): string
    {
        if (!headers_sent()) {
            http_response_code($status);
            foreach ($headers as $name => $value) {
                header("$name: $value");
            }
            header('Content-Type: ' . $contentType);
        }
        return $body;
    }

    /** @param array<string, string> $headers */
    public static function html(string $body, int $status = 200, array $headers = []): string
    {
        return self::with($status, 'text/html; charset=utf-8', $body, $headers);
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): string
    {
        return self::with($status, 'application/json; charset=utf-8', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function redirect(string $to, int $status = 302): string
    {
        if (!headers_sent()) {
            header('Location: ' . $to, true, $status);
        }
        return '';
    }

    public static function back(string $fallback = '/'): string
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $to = ($ref !== '' && str_contains($ref, ($_SERVER['HTTP_HOST'] ?? '___'))) ? $ref : url($fallback);
        return self::redirect($to);
    }

    public static function noContent(): string
    {
        http_response_code(204);
        return '';
    }
}
