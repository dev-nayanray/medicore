<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Centralized error / exception handling.
 *
 * - Converts all PHP errors to ErrorException (strict, nothing silent).
 * - Logs every uncaught exception to storage/logs.
 * - Renders a friendly error page (or JSON for ajax) in production,
 *   a detailed trace page in debug mode.
 */
final class ErrorHandler
{
    public static function register(bool $debug): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', BASE_PATH . '/storage/logs/php-errors.log');

        set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
            if (!(error_reporting() & $severity)) {
                return false; // @-suppressed
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e) use ($debug): void {
            self::handle($e, $debug);
        });

        register_shutdown_function(static function () use ($debug): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::handle(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']), $debug);
            }
        });
    }

    public static function handle(Throwable $e, bool $debug): void
    {
        // Console context: plain text, no HTML.
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, '✗ ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL
                . '  at ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
            Logger::error('Console ' . get_class($e) . ': ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            exit(1);
        }

        // Never output after output has started when possible; guard headers.
        if (!headers_sent()) {
            http_response_code(self::statusCode($e));
        }

        Logger::error('Uncaught ' . get_class($e) . ': ' . $e->getMessage(), [
            'file'  => $e->getFile() . ':' . $e->getLine(),
            'trace' => $debug ? array_slice(explode("\n", (string) $e->getTraceAsString()), 0, 12) : null,
        ]);

        $wantsJson = (
            (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        );

        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => true,
                'message' => $debug ? $e->getMessage() : 'Server error. Please try again later.',
            ]);
            return;
        }

        $view = match (true) {
            $e instanceof HttpException && $e->getStatusCode() === 404 => 'errors/404',
            $e instanceof HttpException && $e->getStatusCode() === 403 => 'errors/403',
            default => 'errors/500',
        };
        $data = [
            'code'    => self::statusCode($e),
            'debug'   => $debug,
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $debug ? explode("\n", (string) $e->getTraceAsString()) : [],
        ];

        $file = BASE_PATH . '/resources/views/' . $view . '.php';
        if (is_file($file)) {
            try {
                echo View::make($view, $data)->render();
                return;
            } catch (Throwable) {
                // Fall through to plain output below.
            }
        }

        echo '<h1>Error ' . self::statusCode($e) . '</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    }

    private static function statusCode(Throwable $e): int
    {
        return $e instanceof HttpException ? $e->getStatusCode() : 500;
    }
}
