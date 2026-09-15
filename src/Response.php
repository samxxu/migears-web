<?php
declare(strict_types=1);

namespace MiGears\Web;

/**
 * Lightweight HTTP response object.
 * Resource methods return a Response, which is sent uniformly by the framework to avoid direct output.
 */
final class Response
{
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {}

    /**
     * Return a JSON response.
     */
    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            body: json_encode($data, JSON_UNESCAPED_UNICODE),
            status: $status,
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    /**
     * Return an HTML response.
     */
    public static function html(string $html, int $status = 200): self
    {
        return new self(
            body: $html,
            status: $status,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    /**
     * Return a redirect response.
     */
    public static function redirect(string $url, int $status = 302): self
    {
        return new self(status: $status, headers: ['Location' => $url]);
    }

    /**
     * Return an empty response.
     */
    public static function empty(int $status = 204): self
    {
        return new self(status: $status);
    }

    /**
     * Send the response to the browser.
     */
    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->body;
    }
}
