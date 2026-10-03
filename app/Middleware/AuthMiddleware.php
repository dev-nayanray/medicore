<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

/**
 * Blocks unauthenticated requests. The intended URL is remembered so a
 * successful login lands the user where they were heading.
 */
final class AuthMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): string
    {
        if (!Auth::check()) {
            Session::put('_intended', $request->path());
            if ($request->expectsJson()) {
                return Response::json(['error' => true, 'message' => 'Unauthenticated.'], 401);
            }
            return Response::redirect(url('/login'));
        }

        return $next($request);
    }
}
