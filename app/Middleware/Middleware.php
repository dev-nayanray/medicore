<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use Closure;

/**
 * Contract for HTTP middleware. handle() receives the request and the
 * next layer of the onion; it must return the final response string.
 */
interface Middleware
{
    /**
     * @param Closure(Request): string $next
     */
    public function handle(Request $request, Closure $next): string;
}
