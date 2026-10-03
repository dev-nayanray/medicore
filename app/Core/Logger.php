<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File logger writing to storage/logs/app-YYYY-MM-DD.log
 *
 * Logger::info('user logged in', ['user_id' => 1]);
 * -> [2026-10-03 14:22:01] app.INFO: user logged in {"user_id":1}
 */
final class Logger
{
    private const LEVELS = ['debug', 'info', 'warning', 'error', 'critical'];

    public static function log(string $level, string $message, array $context = []): void
    {
        if (!in_array($level, self::LEVELS, true)) {
            $level = 'info';
        }

        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf(
            "[%s] app.%s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : ''
        );

        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function debug(string $message, array $context = []): void    { self::log('debug', $message, $context); }
    public static function info(string $message, array $context = []): void     { self::log('info', $message, $context); }
    public static function warning(string $message, array $context = []): void  { self::log('warning', $message, $context); }
    public static function error(string $message, array $context = []): void    { self::log('error', $message, $context); }
    public static function critical(string $message, array $context = []): void { self::log('critical', $message, $context); }
}
