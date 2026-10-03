<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Keeps authenticated users away from guest pages (login/register).
 */
final class GuestMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): string
    {
        if (Auth::check()) {
            return Response::redirect(url('/'));
        }

        return $next($request);
    }
}
