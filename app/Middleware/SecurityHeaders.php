<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use Closure;

/**
 * Adds defensive HTTP security headers to every response.
 * Applied globally from the front controller.
 */
final class SecurityHeaders implements Middleware
{
    public function handle(Request $request, Closure $next): string
    {
        if (!headers_sent()) {
            header('X-Frame-Options: DENY');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('X-XSS-Protection: 0'); // modern browsers; XSS handled by CSP below
            header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
            header_remove('X-Powered-By');

            // CSP: assets are served same-origin (vendored locally), so the
            // policy stays tight. 'unsafe-inline' is required by Tailwind's
            // runtime CSS injection and inline SVG styles.
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
                . "style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; "
                . "connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        }

        return $next($request);
    }
}
