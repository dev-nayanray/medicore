<?php

declare(strict_types=1);

/**
 * Application configuration. Values read from the environment with
 * sensible fallbacks so a missing key never breaks the boot sequence.
 */

use App\Core\Env;

return [
    'name'     => Env::get('APP_NAME', 'MediCore HMS'),
    'env'      => Env::get('APP_ENV', 'local'),
    'debug'    => filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url'      => rtrim(Env::get('APP_URL', 'http://localhost:8090'), '/'),
    'key'      => Env::get('APP_KEY', ''),
    'timezone' => Env::get('APP_TIMEZONE', 'UTC'),
    'version'  => '1.0.0-phase1',
];
