<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use RuntimeException;

/**
 * Middleware alias registry — maps short names used in route definitions
 * to middleware classes.
 *
 * ->middleware('auth')            -> AuthMiddleware
 * ->middleware('can:users.view')  -> CanMiddleware (parameterized)
 */
final class Alias
{
    /** @var array<string, class-string> */
    private const MAP = [
        'auth'            => AuthMiddleware::class,
        'guest'           => GuestMiddleware::class,
        'csrf'            => VerifyCsrfToken::class,
        'can'             => CanMiddleware::class,
        'secure'          => SecurityHeaders::class,
        'password_current' => PasswordCurrentMiddleware::class,
    ];

    public static function resolve(string $alias): Middleware
    {
        [$name, $param] = array_pad(explode(':', $alias, 2), 2, null);
        $class = self::MAP[$name] ?? null;

        if ($class === null) {
            throw new RuntimeException("Unknown middleware alias [{$name}].");
        }

        return new $class($param);
    }
}
