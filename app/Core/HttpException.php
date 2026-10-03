<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * HTTP-aware exception — carries a status code and optional header bag.
 * Thrown by the router (404/405), middleware (403) and controllers.
 */
class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly int $status,
        string $message = '',
        private readonly array $headers = []
    ) {
        parent::__construct($message, $status);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public static function notFound(string $message = 'Page not found.'): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = 'You do not have permission to perform this action.'): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = 'Authentication required.'): self
    {
        return new self(401, $message);
    }

    public static function serverError(string $message = 'Internal server error.'): self
    {
        return new self(500, $message);
    }
}
