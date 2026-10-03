<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

/**
 * Blocks navigation until an admin-forced password change is completed.
 * The change-password screen itself (and logout) must stay reachable.
 */
final class PasswordCurrentMiddleware implements Middleware
{
    private const ALLOWED_PREFIXES = ['/change-password', '/logout'];

    public function handle(Request $request, Closure $next): string
    {
        if (!Auth::mustChangePassword()) {
            return $next($request);
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($request->path(), $prefix)) {
                return $next($request);
            }
        }

        Session::flash('warning', 'Please set a new password before continuing.');
        return Response::redirect(url('/change-password'));
    }
}
