<?php

declare(strict_types=1);

namespace App\Core;

/**
 * A single registered route. Created by Router::add() — application code
 * only interacts with these through the fluent methods on registration.
 */
final class Route
{
    private ?Router $router = null;
    private string $compiled;
    private ?string $name = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly mixed $handler,
        private array $middleware = [],
    ) {
        $this->compiled = self::compile($path);
    }

    /** @internal called by Router::add() */
    public function bind(Router $router): void
    {
        $this->router = $router;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        $this->router?->registerNamed($name, $this->path);
        return $this;
    }

    public function middleware(string ...$aliases): self
    {
        $this->middleware = array_values(array_unique(array_merge($this->middleware, $aliases)));
        return $this;
    }

    /**
     * Match a request against this route.
     *
     * @return null|false|array<string,string>
     *   null  -> path did not match
     *   false -> path matched but HTTP method did not
     *   array -> extracted path parameters
     */
    public function match(string $method, string $path): null|bool|array
    {
        if (!preg_match($this->compiled, $path, $m)) {
            return null;
        }
        if ($method !== $this->method && !($method === 'HEAD' && $this->method === 'GET')) {
            return false;
        }

        $params = [];
        foreach ($m as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /** @return string[] */
    public function middlewareList(): array
    {
        return $this->middleware;
    }

    private static function compile(string $path): string
    {
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $regex . '$#';
    }
}
