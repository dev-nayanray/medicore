<?php

declare(strict_types=1);

/**
 * Global helper functions used across controllers and views.
 * All output helpers escape by default — never echo raw user data.
 */

use App\Core\Auth;
use App\Core\Config;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\SettingService;

// ------------------------------------------------------------------
// Configuration
// ------------------------------------------------------------------
function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

// ------------------------------------------------------------------
// Output escaping (XSS defence)
// ------------------------------------------------------------------
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ------------------------------------------------------------------
// URLs & assets
// ------------------------------------------------------------------
/** Application URL for a path: url('admin/users')
 *
 * The host is derived from the current request so assets and links stay
 * same-origin however the app is reached (localhost vs 127.0.0.1 vs a
 * LAN IP) — cross-origin asset hosts would trip the CSP. CLI contexts
 * fall back to APP_URL. Sub-path deploys (e.g. /medicore/public) are
 * honoured via SCRIPT_NAME.
 */
function url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) {
            $base = rtrim((string) config('app.url', ''), '/');
        } else {
            $scheme = (($_SERVER['HTTPS'] ?? '') === 'on'
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
            $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
            $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir;
        }
    }
    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

/** Asset URL with cache-busting version param. */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : (string) config('app.version');
    return url('assets/' . ltrim($path, 'assets/')) . '?v=' . $version;
}

/** Current route helper: route('dashboard') resolves named route lazily. */
function route(string $name, array $params = []): string
{
    return $GLOBALS['__router']?->route($name, $params)
        ?? throw new RuntimeException("Router not available for route [{$name}].");
}

// ------------------------------------------------------------------
// CSRF
// ------------------------------------------------------------------
function csrf_token(): string
{
    return Session::csrfToken();
}

/** Hidden input for forms: <?= csrf_field() ?> */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

// ------------------------------------------------------------------
// Auth & authorization
// ------------------------------------------------------------------
function auth_user(): ?array
{
    return Auth::user();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function is_superuser(): bool
{
    return Auth::hasRole('super-admin');
}

// ------------------------------------------------------------------
// Views & partials (used inside templates: component('badge', [...]))
// ------------------------------------------------------------------
function component(string $name, array $data = []): string
{
    return View::make('components/' . $name, $data)->render();
}

function view(string $template, array $data = []): string
{
    return View::make($template, $data)->render();
}

// ------------------------------------------------------------------
// Flash / old input
// ------------------------------------------------------------------
function old(string $key, mixed $default = ''): string
{
    static $old = null;
    if ($old === null) {
        $old = Session::pullOld();
    }
    return e($old[$key] ?? $default);
}

/** Un-escaped variant for array inputs (multi-checkboxes). */
function old_raw(string $key, mixed $default = []): mixed
{
    static $old = null;
    if ($old === null) {
        $old = Session::pullOld();
    }
    return $old[$key] ?? $default;
}

/** Validation errors from the previous request: error('email') */
function error(string $key): ?string
{
    static $errors = null;
    if ($errors === null) {
        $errors = Session::get('_errors', []);
        Session::forget('_errors');
    }
    return isset($errors[$key]) ? (string) $errors[$key] : null;
}

// ------------------------------------------------------------------
// Formatting
// ------------------------------------------------------------------
function format_money(null|string|int|float $amount, ?string $currency = null): string
{
    $currency ??= (string) setting('currency', 'USD');
    $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'BDT' => '৳', 'INR' => '₹'];
    $symbol = $symbols[$currency] ?? $currency . ' ';
    return $symbol . number_format((float) ($amount ?? 0), 2);
}

function format_date(?string $datetime, string $format = 'M j, Y g:i A'): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts === false ? '—' : date($format, $ts);
}

function time_ago(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $diff = time() - (int) strtotime($datetime);
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' hr ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    }
    return date('M j, Y', (int) strtotime($datetime));
}

/** Two-letter initials for avatar chips: "Sarah Chen" -> "SC" */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

// ------------------------------------------------------------------
// Settings (DB-backed hospital settings)
// ------------------------------------------------------------------
function setting(string $key, mixed $default = null): mixed
{
    return SettingService::get($key, $default);
}

// ------------------------------------------------------------------
// Misc
// ------------------------------------------------------------------
function str_limit(string $value, int $limit = 80): string
{
    return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit - 1) . '…';
}

/** Redirect helper for controllers: return redirect('/login'); */
function redirect(string $to): string
{
    return Response::redirect($to[0] === '/' ? url($to) : $to);
}

function redirect_to_login_anticipated(): string
{
    return Response::redirect(url('/login'));
}
