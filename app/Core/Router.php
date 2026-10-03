<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * HTTP router with dynamic segments, named routes, middleware and groups.
 *
 * $router->get('/users/{id}', [UserController::class, 'show'])
 *        ->name('users.show')
 *        ->middleware('can:users.view');
 *
 * $router->group(['prefix' => '/admin', 'middleware' => ['auth']], fn($r) => ...);
 */
final class Router
{
    /** @var array<int, Route> */
    private array $routes = [];
    /** @var array<string, string> name -> path pattern */
    private array $named = [];
    /** @var array{prefix:string, middleware:string[]} */
    private array $groupStack = ['prefix' => '', 'middleware' => []];
    private Request $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
    }

    // ------------------------------------------------------------------
    // Registration
    // ------------------------------------------------------------------
    public function get(string $path, callable|array $handler): Route { return $this->add('GET', $path, $handler); }
    public function post(string $path, callable|array $handler): Route { return $this->add('POST', $path, $handler); }
    public function put(string $path, callable|array $handler): Route { return $this->add('PUT', $path, $handler); }
    public function patch(string $path, callable|array $handler): Route { return $this->add('PATCH', $path, $handler); }
    public function delete(string $path, callable|array $handler): Route { return $this->add('DELETE', $path, $handler); }

    public function add(string $method, string $path, callable|array $handler): Route
    {
        $g = $this->groupStack;
        $prefix = trim($g['prefix'], '/');
        $trimmedPath = trim($path, '/');
        $fullPath = $prefix === '' ? ('/' . $trimmedPath) : ('/' . $prefix . '/' . $trimmedPath);
        $fullPath = rtrim($fullPath, '/') ?: '/';

        $route = new Route(strtoupper($method), $fullPath, $handler, $g['middleware']);
        $route->bind($this);
        $this->routes[] = $route;

        return $route;
    }

    public function group(array $attributes, Closure $callback): void
    {
        $previous = $this->groupStack;
        $this->groupStack = [
            'prefix'     => trim($previous['prefix'] . '/' . trim((string) ($attributes['prefix'] ?? ''), '/'), '/'),
            'middleware' => array_values(array_unique(array_merge(
                $previous['middleware'],
                (array) ($attributes['middleware'] ?? [])
            ))),
        ];

        $callback($this);

        $this->groupStack = $previous;
    }

    // ------------------------------------------------------------------
    // Named-route registration (called by Route::name())
    // ------------------------------------------------------------------
    public function registerNamed(string $name, string $pathPattern): void
    {
        $this->named[$name] = $pathPattern;
    }

    /** Build a URL for a named route. */
    public function route(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw HttpException::serverError("Route [{$name}] is not defined.");
        }

        $url = preg_replace_callback('/\{(\w+)\}/', static function (array $m) use (&$params): string {
            $key = $m[1];
            $value = (string) ($params[$key] ?? '');
            unset($params[$key]);
            return rawurlencode($value);
        }, $this->named[$name]) ?? '/';

        $url = '/' . trim($url, '/');
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }

    // ------------------------------------------------------------------
    // Dispatch
    // ------------------------------------------------------------------
    public function dispatch(): string
    {
        $path = $this->request->path();
        $method = $this->request->method();

        $allowedMethods = [];
        foreach ($this->routes as $route) {
            $result = $route->match($method, $path);
            if ($result === null) {
                continue;                       // path did not match
            }
            if ($result === false) {
                $allowedMethods[] = $route->method();  // path matched, method did not
                continue;
            }
            return $this->runRoute($route, $result);
        }

        if ($allowedMethods !== []) {
            if (!headers_sent()) {
                header('Allow: ' . implode(', ', array_values(array_unique($allowedMethods))));
            }
            throw HttpException::notFound('Method not allowed.');
        }

        throw HttpException::notFound('The page you are looking for could not be found.');
    }

    /**
     * Run a matched route through its middleware pipeline (onion pattern).
     *
     * @param array<string, string> $params
     */
    private function runRoute(Route $route, array $params): string
    {
        $core = function (Request $req) use ($route, $params): string {
            [$class, $action] = $route->handler;
            $controller = new $class();
            if (!method_exists($controller, $action)) {
                throw HttpException::serverError("Handler {$class}::{$action} does not exist.");
            }
            $result = $controller->{$action}($req, ...array_values($params));
            return is_string($result) ? $result : '';
        };

        $pipeline = array_reduce(
            array_reverse($route->middlewareList()),
            static function (Closure $next, string $alias): Closure {
                return static function (Request $req) use ($next, $alias): string {
                    return \App\Middleware\Alias::resolve($alias)->handle($req, $next);
                };
            },
            $core
        );

        return $pipeline($this->request);
    }

    /** @return array<int, Route> */
    public function routes(): array
    {
        return $this->routes;
    }
}
