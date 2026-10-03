<?php

declare(strict_types=1);

/**
 * MediCore front controller — the ONLY PHP entry point exposed by the
 * web server (document root must be this /public directory).
 */

// PHP built-in dev server: serve real static files directly.
if (php_sapi_name() === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Request;
use App\Core\Router;
use App\Core\Session;

// 1. Secure session
Session::start();

// 2. Router with the web route table
$router = new Router(new Request());
require BASE_PATH . '/routes/web.php';

// 3. Make named-route resolution available to the route() helper.
$GLOBALS['__router'] = $router;

// 4. Global middleware wraps the dispatch: security headers + CSRF.
$pipeline = static function (Request $req) use ($router): string {
    return $router->dispatch();
};

$next = $pipeline;
foreach (array_reverse(['secure', 'csrf']) as $alias) {
    $next = static function (Request $req) use ($next, $alias): string {
        return \App\Middleware\Alias::resolve($alias)->handle($req, $next);
    };
}

echo $next(new Request());
