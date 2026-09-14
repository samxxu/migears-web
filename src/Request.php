<?php
declare(strict_types=1);

namespace TinyGears\Web;

/**
 * Lightweight HTTP request object.
 * Encapsulates request data to avoid direct use of superglobals and facilitates unit testing.
 */
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly array $server = [],
    ) {}

    /**
     * Create a Request object from PHP global variables.
     */
    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$name] = $v;
            }
        }

        $body = $_POST;
        if (empty($body) && in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['PUT', 'PATCH', 'DELETE'])) {
            $input = file_get_contents('php://input') ?: '';
            $body = (array) json_decode($input, true) ?: [];
        }

        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            path: parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            query: $_GET,
            body: $body,
            headers: $headers,
            server: $_SERVER,
        );
    }

    /**
     * Get a request header (case-insensitive).
     */
    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }
}
