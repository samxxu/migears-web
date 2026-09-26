<?php
declare(strict_types=1);

namespace MiGears\Web;

/**
 * Lightweight HTTP request object.
 * Encapsulates request data to avoid direct use of superglobals and facilitates unit testing.
 *
 * Properties are publicly mutable for extension — subclasses and middleware
 * can decorate the request (e.g. add parsed attributes, normalized headers).
 */
final class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $body = [],
        public array $headers = [],
        public array $server = [],
        public array $files = [],
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
        // CONTENT_TYPE / CONTENT_LENGTH don't carry the HTTP_ prefix; collect them explicitly
        foreach (['CONTENT_TYPE', 'CONTENT_LENGTH'] as $name) {
            if (isset($_SERVER[$name])) {
                $headers[strtolower(str_replace('_', '-', $name))] = $_SERVER[$name];
            }
        }

        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            path: parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            query: $_GET,
            body: self::parseBody($_POST, (string) (file_get_contents('php://input') ?: '')),
            headers: $headers,
            server: $_SERVER,
            files: $_FILES,
        );
    }

    /**
     * Resolve the request body.
     *
     * Prefers the (form-)parsed $_POST. When $_POST is empty but a raw body is
     * present (e.g. application/json POST/PUT/PATCH/DELETE), parses it as JSON.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function parseBody(array $post, string $rawInput): array
    {
        if (!empty($post)) {
            return $post;
        }
        if ($rawInput === '') {
            return [];
        }
        $decoded = json_decode($rawInput, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get a request header (case-insensitive).
     */
    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }
}
