<?php

declare(strict_types=1);

/**
 * Authentication / throttling configuration.
 */

use App\Core\Env;

return [
    'session' => [
        'name'     => Env::get('SESSION_NAME', 'MEDICORE_SESSION'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 7200),
        'httponly' => true,
        'samesite' => 'Lax',
        // Automatically true when served over HTTPS.
        'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    ],
    'throttle' => [
        'max_attempts'    => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) Env::get('LOGIN_LOCKOUT_MINUTES', 10),
    ],
    // Role slugs considered "staff" for dashboard workforce metrics.
    'staff_roles' => ['doctor', 'nurse', 'receptionist', 'pharmacist', 'lab-technician', 'accountant', 'administrator'],
];
