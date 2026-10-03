<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use Closure;

/**
 * Verifies the CSRF token on every state-changing request.
 * Accepts the token from the hidden form field or the X-CSRF-Token header.
 */
final class VerifyCsrfToken implements Middleware
{
    public function handle(Request $request, Closure $next): string
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $token = $request->input('_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        if (!Session::verifyCsrf(is_string($token) ? $token : null)) {
            throw new HttpException(419, 'Page expired. Please go back and try again.');
        }

        return $next($request);
    }
}
