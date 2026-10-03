<?php

/**
 * MediCore application bootstrap.
 *
 * Loads the environment, registers the PSR-4 autoloader, boots the
 * error handler / logger and exposes shared configuration to the rest
 * of the application. Every entry point (web front controller, console
 * commands, tests) includes this file exactly once.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// ---------------------------------------------------------------------------
// Autoloader — PSR-4:  App\Core\Router  ->  app/Core/Router.php
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

// ---------------------------------------------------------------------------
// Environment
// ---------------------------------------------------------------------------
App\Core\Env::load(BASE_PATH . '/.env');

// ---------------------------------------------------------------------------
// Configuration (immutable snapshot available via config())
// ---------------------------------------------------------------------------
App\Core\Config::init([
    'app'      => require BASE_PATH . '/config/app.php',
    'database' => require BASE_PATH . '/config/database.php',
    'auth'     => require BASE_PATH . '/config/auth.php',
]);

// ---------------------------------------------------------------------------
// Global helper functions (e(), url(), csrf_field(), ...)
// ---------------------------------------------------------------------------
require BASE_PATH . '/app/Helpers/functions.php';

// ---------------------------------------------------------------------------
// Error handling & logging (only register once, and never in tests where
// PHPUnit-style runners install their own handlers).
// ---------------------------------------------------------------------------
if (!defined('MEDICORE_BOOTSTRAPPED')) {
    App\Core\ErrorHandler::register((bool) config('app.debug'));
    define('MEDICORE_BOOTSTRAPPED', true);
}

date_default_timezone_set((string) config('app.timezone', 'UTC'));
