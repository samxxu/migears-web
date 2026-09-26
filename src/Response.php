<?php
declare(strict_types=1);

namespace MiGears\Web;

/**
 * Lightweight HTTP response object.
 * Resource methods return a Response, which is sent uniformly by the framework to avoid direct output.
 *
 * Properties are publicly mutable for extension — subclasses can add helpers,
 * and callers can chain withHeader() / withStatus() to build up the response.
 */
final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {}

    /**
     * Return a JSON response.
     *
     * If json_encode fails (e.g. NAN, INF, resources, depth limit), the
     * response status is 500 and the body contains a JSON-encodable error
     * description — the framework degrades gracefully instead of throwing a
     * TypeError.
     */
    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $status = 500;
            $body = json_encode([
                'error' => 'json_encode failed',
                'code' => json_last_error(),
                'message' => json_last_error_msg(),
            ], JSON_UNESCAPED_UNICODE);
        }
        return new self(
            body: $body,
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
     * Set a response header. Returns $this for chaining.
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set the HTTP status code. Returns $this for chaining.
     */
    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
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
