<?php

declare(strict_types=1);

/**
 * Database configuration — single centralized PDO connection profile.
 */

use App\Core\Env;

return [
    'connection' => Env::get('DB_CONNECTION', 'mysql'),
    'host'       => Env::get('DB_HOST', '127.0.0.1'),
    'port'       => (int) Env::get('DB_PORT', 3306),
    'database'   => Env::get('DB_DATABASE', 'medicore'),
    'username'   => Env::get('DB_USERNAME', 'root'),
    'password'   => Env::get('DB_PASSWORD', ''),
    'charset'    => 'utf8mb4',
    'collation'  => 'utf8mb4_unicode_ci',
    // Fail fast on SQL errors instead of silently continuing.
    'options'    => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ],
];
