<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP request wrapper around the superglobals.
 */
final class Request
{
    private ?array $jsonCache = null;

    public function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // Allow HTML forms to emulate PUT/PATCH/DELETE via a _method field.
        if ($method === 'POST') {
            $override = strtoupper((string) $this->input('_method'));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $method;
    }

    public function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return '/' . trim($uri, '/');
    }

    /** @return array<string, mixed> merged POST body + query string (POST wins) */
    public function all(): array
    {
        $body = $this->jsonCache ?? $_POST;
        return array_merge($_GET, $body);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $body = $this->jsonCache ?? $_POST;
        if (array_key_exists($key, $body)) {
            return $body[$key];
        }
        return $_GET[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function json(): ?array
    {
        if ($this->jsonCache === null && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            $this->jsonCache = is_array($decoded) ? $decoded : [];
        }
        return $this->jsonCache ?? null;
    }

    public function isAjax(): bool
    {
        return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function expectsJson(): bool
    {
        return $this->isAjax() || str_starts_with($this->path(), '/api/');
    }

    public function ip(): string
    {
        // Direct REMOTE_ADDR only — do NOT trust spoofable X-Forwarded-For
        // unless you sit behind a trusted reverse proxy.
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
    }

    public function fullUrl(): string
    {
        $scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
    }

    public function url(): string
    {
        return $this->path();
    }
}
