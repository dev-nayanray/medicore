<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use Closure;

/**
 * Permission gate: ->middleware('can:users.view')
 * Super admins bypass all permission checks (see Auth::can()).
 */
final class CanMiddleware implements Middleware
{
    public function __construct(private readonly ?string $permission = null)
    {
    }

    public function handle(Request $request, Closure $next): string
    {
        if ($this->permission === null || Auth::can($this->permission)) {
            return $next($request);
        }

        throw HttpException::forbidden(
            "You do not have the [{$this->permission}] permission required for this page."
        );
    }
}
